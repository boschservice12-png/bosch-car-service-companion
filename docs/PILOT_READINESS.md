# Pilot readiness — ghid de operare

Acest document descrie stabilizarea pentru un **pilot intern controlat**: cele
șase blocuri de întărire, variabilele de mediu, comportamentul la runtime și
procedurile de operare. Nu introduce module de business noi — doar aduce
sistemul într-o stare în care poate fi pornit și rulat previzibil.

> Rezumat pentru operatori: nimic nu rămâne blocat „în tăcere". Documentele se
> scanează printr-un worker, notificările spun adevărul despre livrare, accesul
> la vehicul se dă doar cu un cod emis de service, 2FA nu poate fi reluat, iar
> readiness devine roșu când o dependență critică pică.

---

## Variabile de mediu (nou / relevant pentru pilot)

| Variabilă | Implicit | Rol |
|---|---|---|
| `STORAGE_DRIVER` | `local` | `local` = disc privat (dev/demo, cu volum persistent); `s3` = bucket S3-compatibil (MinIO / AWS) în producție. |
| `S3_ENDPOINT` | `http://minio:9000` | Endpoint-ul S3/MinIO (folosit când `STORAGE_DRIVER=s3`). |
| `S3_BUCKET` | `bcsc-documents` | Bucketul privat pentru documente. |
| `S3_KEY` / `S3_SECRET` | *(gol)* | Credențiale de acces S3/MinIO. |
| `S3_REGION` | `us-east-1` | Regiunea folosită la semnătura SigV4 (MinIO acceptă orice valoare). |
| `MESSENGER_TRANSPORT_DSN` | `doctrine://default?auto_setup=1` (demo) / `auto_setup=0` (prod) | Transportul async consumat de worker. În producție `auto_setup=0`: migrațiile dețin schema (`messenger_messages` e creată de `Version20260715234015`). Ordinea de pornire e garantată de serviciul one-shot `migrate`. |
| `LEGACY_PLATE_CLAIM_ENABLED` | `false` | Revendicarea contului doar cu numărul de înmatriculare — **dezactivată** (vezi Blocul 3). Nu activați în pilot/producție. |
| `APP_SECRET` | *(setați!)* | Readiness pică dacă e gol sau conține `change` / `dev-secret`. |

---

## Blocul 1 — Worker Messenger (demo)

Scanarea antimalware a documentelor și trimiterea notificărilor sunt joburi
**asincrone**: se pun pe transportul `async` și trebuie consumate de un worker.
Fără worker, un document încărcat ar rămâne veșnic `PENDING` (neservibil).

- `compose.demo.yaml` are un serviciu `worker` care rulează
  `messenger:consume async` cu `--time-limit` / `--memory-limit` și
  `restart: unless-stopped` (procesul iese periodic, compose îl repornește — nu
  „moare în tăcere").
- Mesajele care eșuează de `max_retries` ori ajung pe transportul `failed`
  (`doctrine://default?queue_name=failed`), inspectabil, nu se pierd.

```bash
docker compose -f compose.demo.yaml logs -f worker
docker compose -f compose.demo.yaml exec backend php bin/console messenger:stats
docker compose -f compose.demo.yaml exec backend php bin/console messenger:failed:show
```

---

## Blocul 2 — Integritatea verificării scadențelor

O scadență are o **proveniență** (`source` + `verifiedAt` / `verifiedBy`).

- Când un **client** modifică `validFrom`, `expiresAt` sau documentul unei
  scadențe, `source` devine `CLIENT` și verificarea se resetează
  (`verifiedAt` / `verifiedBy` → null) — **chiar dacă rândul era `SERVICE`
  neverificat**. O valoare atinsă de client nu poate rămâne marcată „verificat
  de service".
- Editarea **doar a notei** nu resetează verificarea.
- Modificările de **admin** nu verifică automat — verificarea se setează doar la
  o acțiune explicită `verify: true`.
- Fiecare tranziție e auditată cu vechea și noua valoare + motivul resetării.

---

## Blocul 3 — Activare sigură a vehiculului

Numărul de înmatriculare și VIN-ul **nu sunt secrete** → nu mai acordă singure
acces la un vehicul importat. În loc de asta:

1. Service-ul emite un **cod de activare** pentru un vehicul
   (`POST /api/admin/vehicles/{id}/activation-token`). Codul apare **o singură
   dată** în panoul admin.
2. Codul este: aleator (128 biți), stocat doar ca **hash** (SHA-256), cu
   **expirare** (7 zile), **o singură utilizare** și cu **limită de încercări**
   (rate limit pe `activation`).
3. Clientul îl folosește la `POST /api/me/vehicles/activate`. Codul corect leagă
   vehiculul de profilul clientului.
4. La **transfer de proprietate**, rândul de proprietate activ este reatribuit
   noului proprietar (accesul vechiului proprietar se închide). Conflictele →
   `409`.
5. Un cod greșit / expirat / folosit → `422` cu mesaj generic (nu divulgă starea).
6. Totul e auditat (`vehicle.activation_issued`, `vehicle.activation_used`).

Revendicarea legacy prin număr rămâne în cod, dar **dezactivată** în spatele
`LEGACY_PLATE_CLAIM_ENABLED=false`. Nu o activați în pilot.

---

## Blocul 4 — Stare realistă a notificărilor

Model de stare: `PENDING → PROCESSING → { SENT | FAILED | MANUAL_ACTION_REQUIRED | SKIPPED }`.

- `SENT` **doar** la un succes real de la un furnizor automat **sau** la o
  confirmare manuală explicită de admin. Fără furnizor configurat, o notificare
  **nu** ajunge niciodată `SENT` „orb" — devine `MANUAL_ACTION_REQUIRED`
  (sau `SKIPPED` pentru adresele interne `@clienti.local` / `@anonim.local`).
- Livrarea trece printr-un adaptor `NotificationDelivery`. În pilot,
  implementarea implicită (`ManualNotificationDelivery`) nu trimite nimic
  automat — se înlocuiește cu un furnizor real când e configurat.
- **Idempotență / dedup**: notificările poartă un `dedupKey`; o stare terminală
  scurtcircuitează reprocesarea. Retry-ul se face doar pentru furnizori
  automați și eșecuri retriabile.
- Admin poate marca manual ca trimisă
  (`POST /api/admin/notifications/{id}/manually-sent`) cu `sentBy` / `sentAt` /
  canal / notă — auditat.

Lista notificărilor: `GET /api/admin/notifications` (rol service-admin).

---

## Blocul 5 — Protecție anti-replay TOTP

- Ultimul pas TOTP acceptat se **persistă** per utilizator (`users.last_totp_step`).
- Un cod cu **același pas sau mai vechi** este respins — un cod interceptat nu
  poate fi reutilizat în fereastra lui de valabilitate.
- Consumul este **concurrency-safe**: un `UPDATE ... WHERE last_totp_step IS NULL
  OR last_totp_step < :step` condiționat, atomic — două cereri paralele cu
  același cod nu pot trece amândouă.
- Codurile de rezervă sunt **o singură utilizare** și stocate hash-uit.
- Un replay respins este auditat (`identity.2fa_replay_rejected`) cu mesaj
  generic către client.

---

## Blocul 6 — Stocare durabilă + readiness profund

### Stocare
- `STORAGE_DRIVER` comută la runtime între `local` și `s3` (vezi tabelul de mai
  sus). `S3Storage` implementează semnătura AWS SigV4 direct (fără SDK), cu
  adresare path-style pentru compatibilitate MinIO; bucketul e privat, servirea
  trece prin URL-uri semnate + verificare de autorizare, la fel ca varianta
  locală.
- În `compose.demo.yaml`, documentele stau pe volumul persistent `storage_data`,
  partajat de `backend` și `worker` — supraviețuiesc unui `up --build` / restart.

### Liveness vs. readiness (separate)
- `GET /api/health` (**liveness**): procesul trăiește. **Nu** atinge dependențe
  externe → un outage de bază nu declanșează restart-uri în lanț. Mereu `200`.
- `GET /api/health/ready` (**readiness**): aplicația poate SERVI în siguranță.
  Verifică dependențele **critice** — bază de date, stare migrații, storage
  (probă scriere/citire/ștergere), secrete aplicație — plus `messenger` și
  `scanner` (necritice). O dependență critică picată → `503`. Statusuri per
  verificare: `ok` / `degraded` / `failed`; overall `ok` / `degraded` / `failed`.
- **Nu se arată niciodată readiness verde cu o dependență critică jos** — de ex.
  `APP_SECRET` implicit sau migrații neaplicate → `503`.
- `scanner` sondează daemonul ClamAV cu `PING`/`PONG` (timeout scurt, 2s, fără
  transfer de fișier). E **necritic** deliberat: scanerul e fail-closed, deci
  dacă moare, documentele încărcate rămân în așteptare, dar restul API-ului
  (citiri, deadline-uri, istoric) rămâne servibil — instanța nu trebuie scoasă
  din rotație. Fără verificarea asta eșecul era complet tăcut: readiness rămânea
  verde în timp ce procesarea documentelor sta pe loc. Un ClamAV picat arată deci
  `200` cu `"status":"degraded"` și `checks.scanner.status = "failed"` — asta e
  semnalul de urmărit în monitorizare, nu doar codul HTTP.

```bash
curl -s http://localhost:8080/api/health          # {"status":"ok"}
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8080/api/health/ready
```

### Backup / restore
- `infrastructure/backup/backup.sh` — `pg_dump` + arhiva documentelor, cu
  verificare de integritate (`gzip -t`) și retenție.
- `infrastructure/backup/restore.sh` — restaurare scriptată (verifică
  integritatea arhivelor înainte de a scrie ținta). Procedura completă de drill
  (lunar, mediu izolat, RTO/RPO consemnate): `infrastructure/backup/restore.md`.
- `infrastructure/monitoring/healthcheck.sh` verifică liveness + readiness +
  spațiu pe disc + prospețimea backupului.

---

## Rularea suitei de regresie

```bash
./scripts/regression.sh
```

Rulează: teste backend (PHPUnit), lint container (prod + test), lint YAML,
typecheck/lint/build pentru ambele frontend-uri și validarea celor două fișiere
docker compose. Testele Playwright e2e sunt separate (cer stiva pornită) —
vezi `e2e/README.md`.

---

## Limitări cunoscute (pilot)

- **Fără furnizor de notificări automat** în pilot: notificările ajung
  `MANUAL_ACTION_REQUIRED`, nu `SENT`, până când se configurează un furnizor
  real. E o alegere deliberată (Blocul 4), nu un bug.
- **`S3Storage` nu a fost verificat la runtime** în acest mediu (fără MinIO /
  rețea în sandbox). Driverul implicit `local` este testat integral; comutarea
  la `s3` trebuie validată în stagiu înainte de producție.
- E-mailul rămâne un pas manual (decizie de produs), nu automat.
