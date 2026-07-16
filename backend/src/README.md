# Module de domeniu (modular monolith)

Fiecare modul are straturi: `Domain/`, `Application/`, `Infrastructure/`,
`Presentation/`. Comunicarea între module se face **doar prin contracte/interfețe
publice** (fără acces la clasele interne ale altui modul). Nu există folder `Utils`.

| Modul | Responsabilitate | Stadiu scaffold |
|---|---|---|
| `Identity` | Autentificare email+parolă, sesiuni, înregistrare, **2FA TOTP admin** (înrolare + enforcement) | **Sprint 1 + 2FA** ✅ |
| `Customer` | Profil client | **Sprint 1 implementat** ✅ |
| `Vehicle` | Vehicule, VIN, proprietari, autorizare la nivel de obiect | **Sprint 1 implementat** ✅ |
| `Communication` | Conversații & mesaje | planificat (S7) |
| `Deadline` | Scadențe ITP/RCA/rovinietă/roadside, calcul stare | domeniu + calculator ✅ |
| `ServiceHistory` | Istoric service, corecții, PDF | planificat (S3) |
| `QuoteRequest` | Cereri ofertă, mașină de stare (`Domain/QuoteRequestStatus.php`) | mașină de stare ✅ |
| `RoadsideAssistance` | Solicitări asistență rutieră (forwarding = intern + telefon) | planificat (S5) |
| `Mobility` | Solicitări mobilitate | planificat (S5) |
| `DamageClaim` | Dosar de daună (colectare date) | planificat (S6) |
| `Tax` | Taxe & impozite anuale | planificat (S6) |
| `Document` | Upload sigur, validare MIME+extensie+dimensiune, scanare malware, storage privat, URL semnat temporar | **Sprint 1 implementat** ✅ |
| `Notification` | Notificări multi-canal (Messenger async) + entitate `Notification` | **Sprint 1 implementat** ✅ |
| `Audit` | Jurnal de audit before/after (`AuditRecorder`) | **Sprint 1 implementat** ✅ |
| `Settings` | `application_settings` (praguri, texte, WhatsApp, limite upload) | **Sprint 1 implementat** ✅ |
| `Shared` | Eroare standard + listener excepții + CORS | **Sprint 1 implementat** ✅ |
| `System` | Health endpoints (`/api/health`, `/api/health/ready`) | **implementat** ✅ |

## Sprint 1 — API disponibil

| Metodă & rută | Descriere | Acces |
|---|---|---|
| `POST /api/auth/register` | Înregistrare client (email+parolă+consimțământ) | public |
| `POST /api/auth/login` | Autentificare (json_login) | public |
| `POST /api/auth/logout` | Deconectare | autentificat |
| `GET /api/me` | Utilizatorul curent | autentificat |
| `GET /api/vehicles` | Vehiculele proprii | client |
| `POST /api/vehicles` | Adaugă vehicul (VIN validat) | client |
| `GET /api/vehicles/{id}` | Detalii (autorizare la nivel de obiect) | proprietar/admin |
| `PATCH /api/vehicles/{id}` | Actualizează | proprietar/admin |
| `GET /api/health` · `GET /api/health/ready` | Liveness / readiness | public |

| `POST /api/documents` | Upload document (multipart) | autentificat |
| `GET /api/documents/{id}/download-url` | URL temporar semnat | proprietar/admin |
| `GET /api/documents/{id}/raw` | Servire conținut (semnătură+autorizare+CLEAN) | proprietar/admin |
| `GET /api/settings` | Setări publice (WhatsApp, versiune informare) | public |
| `PATCH /api/admin/settings/{key}` | Actualizează o setare | admin |

## Rulare și verificare

```bash
# Dependențe
composer install

# Bază de date + schemă (PostgreSQL)
php bin/console doctrine:migrations:migrate --no-interaction

# Date demo (idempotent): admin@bcsc.ro / client@bcsc.ro, parola Demo1234!
php bin/console app:demo:seed

# Utilizatori individuali
php bin/console app:user:create client@example.ro Parola123          # client
php bin/console app:user:create admin@example.ro Parola123 --admin   # admin

# Teste (mediul de test folosește SQLite implicit — vezi .env.test)
php bin/console doctrine:schema:create --env=test
vendor/bin/phpunit

# Validări
php bin/console lint:container
php bin/console doctrine:schema:validate
```

Codul de domeniu deja livrat este **pur PHP** (fără dependențe de framework) și
este acoperit de teste unitare în `tests/Unit/` (calcul scadențe, tranziții de
stare, validare VIN) — verificate să treacă.
