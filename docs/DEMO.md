# Demo & runbook — Bosch Car Service Companion

Ghid pentru a rula aplicația și a demonstra fluxurile livrate (Sprinturile 1–3),
din **două sesiuni separate: CLIENT și ADMIN**.

## Ce se poate demonstra

| Modul | Client | Service (admin) |
|---|---|---|
| **Scadențe** (ITP / RCA / rovinietă / asistență) | vede stările calculate (valid / expiră curând / expirat), adaugă scadențe, atașează documente | listează vehiculele, validează scadențe, adaugă/atașează documente |
| **Istoric service** | vede istoricul publicat al propriilor vehicule, descarcă documente | creează ciornă → publică; corecțiile păstrează originalul vizibil |
| **Comunicare & oferte** | trimite mesaje / cereri de ofertă cu atașamente, acceptă/refuză oferta | răspunde, trimite oferta (sumă) |
| **Asistență rutieră** | deschide o cerere (locație, problemă, mobilitate, siguranță, telefon, foto), anulează | preia (contact telefonic), schimbă starea |
| **Mobilitate** | cere mașină de înlocuire / taxi / transport, anulează | aprobă / asigură / respinge |
| **Dosar de daună** | deschide un dosar (eveniment, asigurător, poliță, foto) | preia și urmărește starea |
| **Taxe & impozite** | urmărește taxele anuale, le editează, marchează plata declarativ (fără fișiere) | ajustează starea de plată |

Toate acțiunile respectă **autorizarea la nivel de obiect** (un client nu vede datele
altuia) și sunt înregistrate în **auditul** aplicației.

## Conturi demo (după seed)

| Rol | Email | Parolă |
|---|---|---|
| Service (admin) | `admin@bcsc.ro` | `Demo1234!` |
| Client | `client@bcsc.ro` | `Demo1234!` |

Clientul demo are 2 vehicule (BMW Seria 3 `MS01POP`, VW Golf `MS02POP`) cu scadențe în
stări variate, un istoric de service (o înregistrare publicată + o corecție), o cerere
de ofertă cu ofertă trimisă (stare **QUOTED**), plus — pentru primul vehicul — o cerere
de **asistență rutieră** preluată, o solicitare de **mobilitate** aprobată, un **dosar de
daună** în lucru și două **taxe** (impozit auto plătit + taxă de mediu neplătită).

## Rulare

### Varianta cea mai simplă — o singură comandă (Docker)

Pornește **întreaga stivă** (bază de date + backend + ambele frontend-uri, cu date demo):

```bash
docker compose -f compose.demo.yaml up --build
```

Apoi: **Client** http://localhost:3000 · **Admin** http://localhost:3001. Detalii: `demo/README.md`.

### Varianta A — infrastructură în Docker + aplicații locale

```bash
# 1) Infrastructura (PostgreSQL, MinIO, ClamAv, nginx → backend pe :8080)
cd infrastructure/docker && docker compose up -d

# 2) Schema + date demo (în containerul backend)
docker compose exec backend php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec backend php bin/console app:demo:seed

# 3) Frontend client (:3000)
cd ../../apps/customer-web && npm install && npm run dev

# 4) Frontend admin (:3001) — sesiune separată de browser
cd ../service-admin && npm install && npm run dev
```

### Varianta B — totul local (fără Docker)

```bash
# Backend (:8080) — necesită PHP 8.2+ și o bază PostgreSQL accesibilă prin DATABASE_URL
cd backend
composer install
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:demo:seed
php -S 127.0.0.1:8080 -t public

# Frontend client (:3000) și admin (:3001) — ca la Varianta A, pașii 3–4
```

- Client: <http://localhost:3000>  ·  Admin: <http://localhost:3001>
- Frontend-urile proxy-ează `/api` către backend (`NEXT_PUBLIC_API_BASE`, implicit `http://localhost:8080`).
- **Sfat demo:** folosiți două ferestre/profile de browser separate (sau una normală + una incognito),
  ca sesiunile CLIENT și ADMIN să nu se suprascrie.

## Scenariu de demonstrație (≈5 minute)

### 1. Scadențe (CLIENT → ADMIN → CLIENT)
1. **Client** (`client@bcsc.ro`): *Vehicule → MS01POP*. Se văd scadențele: ITP **valid**,
   RCA **expiră curând**, rovinietă **expirată** (culoare + text + zile rămase).
2. Adăugați un document la o scadență (JPG/PNG/PDF, max 10 MB) → apare „în curs de scanare",
   apoi devine descărcabil.
3. **Admin** (`admin@bcsc.ro`): *Vehicule → MS01POP* → **Validează** o scadență introdusă de client.
4. **Client**: reîncărcați — scadența apare marcată ca validată de service.

### 2. Istoric service (ADMIN → CLIENT)
1. **Admin**: *Vehicul → Istoric service → + Adaugă*. Completați data, kilometrajul, tipul
   lucrării, descrierea, piesele, manopera, totalul, garanția → **Salvează ciorna**.
2. Atașați un document/foto, apoi **Publică**.
3. **Client**: *Vehicul → Istoric service* — vede înregistrarea publicată și poate descărca documentul.
4. **Admin**: pe o înregistrare publicată apăsați **Creează corecție**, modificați și publicați.
   **Client**: vede acum atât originalul (marcat „corectat"), cât și corecția — nimic nu se pierde.

### 3. Comunicare & cerere de ofertă (CLIENT → ADMIN → CLIENT)
1. **Client**: *Mesaje → + Nou* → tip **Cerere de ofertă**, subiect, vehicul, mesaj + atașament → **Trimite**.
2. **Admin**: *Mesaje* → deschide firul → completează **suma ofertei** + detalii → **Trimite oferta**.
3. **Client**: firul arată starea **Ofertă trimisă** + suma → **Acceptă** (sau Refuză).
   (Conversația demo pornește deja în starea *QUOTED*, deci se poate accepta direct.)

### 4. Servicii Sprint 4 (CLIENT → ADMIN)
Din pagina **Acasă** a clientului (secțiunea „Servicii") sau din bara de sus a portalului admin:
- **Asistență rutieră** (`/asistenta`): clientul deschide o cerere (locație, problemă, mobilitate,
  siguranță, telefon + foto); **admin** o preia — starea devine „Preluată de service" (contact telefonic direct).
- **Mobilitate** (`/mobilitate`): clientul cere o mașină de înlocuire; **admin** o aprobă / marchează asigurată.
- **Dosar de daună** (`/daune`): clientul deschide un dosar (eveniment, asigurător, poliță, foto);
  **admin** îl preia și îi urmărește starea; documentele se descarcă autorizat.
- **Taxe & impozite** (`/taxe`): clientul urmărește taxele anuale, le editează și marchează plata
  declarativ — nu se încarcă niciun fișier (fără bon fiscal); **admin** poate ajusta starea de plată.

### 5. Izolare & audit (opțional)
- Autentificați un al doilea client și încercați să accesați datele primului — răspuns **403**
  (pe oricare modul: scadențe, istoric, mesaje, asistență, mobilitate, daune, taxe).
- Toate acțiunile de mai sus sunt scrise în tabelul `audit_logs` (before/after, actor, IP).

## Staging

- `infrastructure/deployment/environments.md` descrie variabilele de mediu (DATABASE_URL,
  APP_SECRET, CORS_ALLOW_ORIGIN, CLAMAV_HOST/PORT, storage).
- Pentru staging: rulați migrațiile și `app:demo:seed` o singură dată (comandă **idempotentă** —
  la a doua rulare nu duplică datele), apoi porniți `worker`-ul Messenger pentru scanarea documentelor
  (`php bin/console messenger:consume async`).
- Nu comiteți secrete; folosiți variabile de mediu / secret manager.

## Verificare rapidă (CI local)

```bash
# Backend: teste (SQLite) + validare schemă (PostgreSQL)
cd backend
php bin/console doctrine:schema:create --env=test && vendor/bin/phpunit
php bin/console doctrine:schema:validate           # pe o bază PostgreSQL migrată

# Frontend (ambele aplicații)
cd ../apps/customer-web && npx tsc --noEmit && npx next lint && npx next build
cd ../service-admin  && npx tsc --noEmit && npx next lint && npx next build
```

Pentru un test de browser end-to-end peste cele două sesiuni, vezi `e2e/README.md`.
