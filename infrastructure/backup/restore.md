# Procedură de restaurare (testată periodic)

> Un backup fără restaurare testată nu este un backup. Rulați acest drill lunar
> pe un mediu izolat și consemnați rezultatul în tabelul de la final.

## Rulare rapidă

`restore.sh` este livrat ÎN imaginea de backup, lângă `pg_dump`/`psql`/`mc` —
deci restaurarea se face din același container care a produs mentința, fără
unelte separate:

```bash
# 1) țintă IZOLATĂ (niciodată baza de producție!)
docker compose --env-file .env.prod -f compose.prod.yaml exec db \
  psql -U bcsc -d postgres -c 'CREATE DATABASE restore_drill OWNER bcsc;'

# 2) restaurare (bază + documente în bucket)
docker compose --env-file .env.prod -f compose.prod.yaml run --rm \
  -e DATABASE_URL_RESTORE="postgresql://bcsc:<parola>@db:5432/restore_drill" \
  -e S3_ENDPOINT_RESTORE=http://minio:9000 \
  -e S3_BUCKET_RESTORE=bcsc-documents-restored \
  -e S3_KEY_RESTORE=<cheie> -e S3_SECRET_RESTORE=<secret> \
  --entrypoint restore.sh backup /backups/<timestamp>
```

Fără variabilele `S3_*_RESTORE`, documentele se extrag pe disc local în
`STORAGE_DIR` (pentru `STORAGE_DRIVER=local`). Scriptul acceptă ambele layout-uri
de arhivă: `documents.tar.gz` (producție, din bucket) și `storage.tar.gz` (disc local).

## Restaurare PESTE producție (distructivă)

Când baza de producție trebuie înlocuită cu conținutul unui backup — corupție,
ștergere accidentală, migrație greșită.

```bash
cd /opt/bcss
CONFIRM=RESTAUREZ-PRODUCTIA ./scripts/restore-production.sh /backups/<timestamp>
```

**Nu rulați `restore.sh` direct peste producție.** Nu merge, și e bine că nu
merge: dumpul conține `CREATE TABLE` fără `DROP`, deci psql se oprește la
`relation "application_settings" already exists`. Cu `ON_ERROR_STOP=on` se
oprește la prima instrucțiune, deci nu lasă baza pe jumătate — dar nici nu
restaurează nimic. Schema trebuie golită explicit înainte, iar asta face
`restore-production.sh`.

Ce face, în ordine:

| Pas | Ce | De ce |
|---|---|---|
| 0 | validează arhiva sursă | ca să nu golim producția și abia apoi să descoperim un dump gol |
| 1 | **backup al stării curente** | dacă restaurați din greșeală backupul greșit, ăsta e singurul drum înapoi |
| 2 | oprește `backend`, `worker`, `scheduler` | fără scriitori activi în timpul înlocuirii schemei |
| 3 | închide conexiunile, `DROP SCHEMA public CASCADE` | vezi mai sus |
| 4 | restaurează | |
| 5 | repornește, verifică migrațiile + readiness | |

Garduri: fără `CONFIRM=RESTAUREZ-PRODUCTIA` nu pornește; cu un backup invalid se
oprește **înainte** de a atinge producția; dacă restaurarea eșuează, scriitorii
rămân opriți deliberat (mai bine indisponibil decât pornit pe o schemă parțială).

**Documentele nu se ating implicit** — doar baza de date. Pentru a restaura și
bucketul: `RESTORE_DOCUMENTS=1 CONFIRM=… ./scripts/restore-production.sh …`.
Rețineți că `mc mirror` suprascrie și adaugă, dar **nu șterge** obiectele care
există live și lipsesc din backup; nu e o oglindire exactă.

Verificat pe 2026-08-11 pe o bază populată: cele patru scadențe șterse au revenit,
`doctrine:schema:validate` raportează *in sync*, iar backupul stării stricate a
rămas în `/backups`.

## Dezastru: instanța nu mai există

Scenariul pentru care există copia off-box. Volumul `backups` s-a pierdut odată
cu mașina, deci întâi aducem mentința din bucket, apoi restaurăm din ea. Pe o
mașină nouă e suficient checkout-ul repo-ului + `.env.prod` cu variabilele
`OFFSITE_*`:

```bash
# 1) ce există la distanță?
docker compose --env-file .env.prod -f compose.prod.yaml run --rm \
  --entrypoint fetch-offsite.sh backup --list

# 2) aducem cea mai recentă (verifică integritatea la descărcare)
docker compose --env-file .env.prod -f compose.prod.yaml run --rm \
  --entrypoint fetch-offsite.sh backup --latest

# 3) restaurăm din ce am adus
docker compose --env-file .env.prod -f compose.prod.yaml run --rm \
  -e DATABASE_URL_RESTORE=… -e S3_ENDPOINT_RESTORE=… …\
  --entrypoint restore.sh backup /backups/restaurate/<timestamp>
```

`fetch-offsite.sh` verifică arhivele imediat după descărcare (gzip + marcajul de
final al dumpului), ca o coruperea în tranzit să fie prinsă înainte de restaurare,
nu în mijlocul ei.

## Verificări post-restaurare (obligatorii)

Restaurarea „a rulat fără eroare" nu înseamnă „datele sunt acolo". Verificați:

1. **Număr de rânduri**, sursă vs. restaurat, pe tabelele care contează
   (`users`, `vehicles`, `documents`, `vehicle_deadlines`).
2. **Istoricul migrațiilor** — `SELECT count(*) FROM doctrine_migration_versions;`
   Fără el, următorul deploy reaplică migrații deja aplicate.
3. **Schema se potrivește cu maparea** — `doctrine:schema:validate` → *in sync*.
4. **Integritatea documentelor la nivel de octeți** — `diff -r` între bucketul
   sursă și cel restaurat, nu doar numărul de obiecte.
5. **Readiness** — `GET /api/health/ready` = 200 pe un backend legat la baza
   restaurată.
6. Un client de test își vede vehiculele și descarcă un document.

## Alerte obligatorii
- backup eșuat (cod de ieșire nenul din `backup.sh` / serviciul `backup`);
- **sincronizare off-box eșuată** — codul de ieșire e nenul și când copia locală
  a reușit, dar cea la distanță nu (vezi `OFFSITE_*` în `.env.prod.example`);
- niciun backup în ultimele 26h (`healthcheck.sh` cu `BACKUP_DIR`);
- storage indisponibil / disc peste prag (`healthcheck.sh`).

---

## Registrul drill-urilor

| Data | Mediu | Volum | RTO măsurat | RPO | Rezultat |
|---|---|---|---|---|---|
| 2026-08-04 | local izolat (Docker, Apple Silicon) | 8 KB DB (20 migrații, 2 utilizatori, 2 vehicule, 4 scadențe) + 5 documente / 1 MB | backup < 1 s, restaurare ~1 s | 24 h (vezi mai jos) | **Trecut** — rânduri identice, 20 de migrații păstrate, documente identice pe octeți (`diff -r`), `schema:validate` *in sync* |
| 2026-08-04 | local izolat — drill de **dezastru** (backupuri locale șterse, recuperare DOAR din bucket) | 8 KB DB + 3 documente / 441 KB | fetch + restaurare ~2 s | 24 h | **Trecut** — `--list` → `--latest` → `restore.sh`; 2 vehicule, 20 de migrații, documente identice pe octeți. Depozitul off-box a fost MinIO local ca substitut S3-compatibil, **nu** Lightsail. |
| 2026-08-11 | **PRODUCȚIE** (Lightsail eu-central-1) — backup → bucket `backup-bcss` → fetch → restaurare într-o bază izolată pe aceeași instanță | 6923 B DB (1 utilizator, 0 vehicule), 0 documente | ciclu complet sub 1 min | 24 h | **Trecut** — `off-box ellenőrzés rendben (6923 B)`; restaurat DIN copia adusă din Lightsail; `users/vehicles/deadlines/migratii` identice cu producția. Versionarea obiectelor e activă pe bucket. |

### Ce a găsit primul drill

Procedura nu mai fusese niciodată executată. A scos la iveală trei defecte reale,
toate corectate în același commit:

1. **`pg_dump` nu accepta DSN-ul din `.env.prod.example`.** `serverVersion` și
   `charset` sunt parametri Doctrine, nu libpq → `invalid URI query parameter`.
2. **Eșecul era invizibil.** `pg_dump … | gzip > f` întoarce codul de ieșire al
   *gzip*-ului, deci un dump eșuat producea o arhivă goală de 20 de octeți, pe
   care `gzip -t` o valida. Backupul raporta succes cu baza de date lipsă.
3. **Restaurarea pierdea toate documentele.** `backup-cron.sh` scrie
   `documents.tar.gz`; `restore.sh` căuta `storage.tar.gz`, nu îl găsea, afișa un
   avertisment și ieșea cu **0** — restaurând doar baza.

Combinate, primele două înseamnă că mentințele de producție de dinaintea acestei
corecții **trebuie considerate lipsite de bază de date**. Verificați dimensiunea
arhivelor existente: `ls -lh /backups/*/db.sql.gz` — orice fișier de ~20 de octeți
este gol.

### Ce a găsit drill-ul de producție

Rulat pe 2026-08-11, a confirmat că toate cele 7 mentințe existente (05–11 august)
aveau `db.sql.gz` de **20 de octeți** — adică baza de date a producției nu fusese
niciodată salvată. Documentele erau la fel: bucketul e gol, deci `tar` pica și
ducea cu el întreaga rulare.

A mai scos la iveală două defecte care apar DOAR pe producție, nu local:

1. **Imaginea de backup nu avea certificate CA.** `postgres:16` nu instalează
   `ca-certificates`, deci `mc` respingea orice endpoint HTTPS cu
   `x509: certificate signed by unknown authority`. Invizibil local, unde ținta
   off-box era MinIO pe `http://`.
2. **nginx nu re-rezolva upstream-ul.** Recrearea containerului `backend` îi dă
   un IP nou, dar `fastcgi_pass backend:9000` (nume literal) rămânea pe cel vechi
   → 502 pe tot `/api`, cu backend-ul sănătos. Vezi comentariul din
   `infrastructure/nginx/default.conf`.

### Despre cifre

**RTO-ul măsurat este un prag inferior, nu o promisiune.** A fost obținut pe un
set de date minuscul, pe un laptop, cu baza și bucketul în aceeași rețea Docker.
Ce demonstrează este că *procedura* funcționează cap-coadă și că verificările
prind regresiile. RTO-ul real pe producție scalează cu volumul de documente
(`mc mirror` domină) și trebuie remăsurat pe server, cu date reale, înainte de a
promite ceva unui client.

**RPO = 24 h**, structural: serviciul `backup` rulează o dată pe zi, la
`BACKUP_HOUR` (implicit 03:00 UTC). Nu e o măsurătoare, e o consecință a
programului. Pentru evidențe de vehicule ale unor persoane fizice, o pierdere de
până la o zi de muncă e o decizie de produs care merită confirmată explicit; dacă
nu e acceptabilă, opțiunile sunt backup mai des sau WAL archiving continuu.
