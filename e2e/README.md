# E2E de browser (CLIENT + ADMIN)

Teste Playwright care conduc fluxul real prin UI, din **două sesiuni** (client și
service), împotriva datelor demo. Complementare testelor funcționale din backend
(`backend/tests/Functional`), care rulează deja în CI.

> Aceste teste **nu** fac parte (încă) din CI — necesită stiva completă pornită.
> Rulați-le local înainte de un demo, sau adăugați-le într-un workflow dedicat.

## Precondiții

Stiva pornită și datele demo încărcate (vezi `docs/DEMO.md`). Rețetă rapidă,
fără Docker (PostgreSQL local + PHP built-in server):

```bash
# 1) Baza de date: backend/.env.local cu DATABASE_URL către PostgreSQL-ul local, apoi:
cd backend
php bin/console doctrine:migrations:migrate -n
php bin/console app:demo:seed          # idempotent
php -S 127.0.0.1:8080 -t public public/index.php &

# 2) Aplicațiile (build o singură dată, apoi start):
cd ../apps/customer-web  && npx next build && npx next start -p 3000 &
cd ../service-admin      && npx next build && npx next start -p 3001 &
```

## Rulare

```bash
cd e2e
npm install
npx playwright test
# sau, cu URL-uri personalizate:
CLIENT_URL=http://localhost:3000 ADMIN_URL=http://localhost:3001 npx playwright test
```

- Pe mașini cu un Chromium deja instalat (altă versiune decât cea cerută de
  Playwright), NU rulați `playwright install` — indicați executabilul:
  `CHROMIUM_PATH=/opt/pw-browsers/chromium npx playwright test`.
- `npx playwright test --list` listează testele fără a porni browserul.

## Ce acoperă (P1-08)

- **client-admin** — fluxul demo: CLIENT vede vehiculele (`MS01POP`), scadențele,
  istoricul publicat și conversația demo; ADMIN vede vehiculele clienților,
  conversațiile și cererea de ofertă din inbox-ul de oferte.
- **client-flows** — cap-coadă pe cont NOU: înregistrare liberă → vehicul propriu →
  taxă → plată parțială declarativă (fără fișiere) → comutatorul de limbă RO→HU
  (aplicat imediat + persistat la reîncărcare). Plus mesageria în ambele sensuri:
  clientul scrie, adminul răspunde, clientul vede răspunsul (două contexte de
  browser separate).
- **admin-flows** — căutarea din panoul principal pe 3 câmpuri (nume / număr /
  VIN, normalizate, combinate cu ȘI) și navigarea modulelor (taxe, securitate).
- **two-factor** — P0-06 prin interfața reală: înrolare 2FA (parolă → secret →
  cod TOTP calculat în test conform RFC 6238 → coduri de rezervă), re-login cu
  provocare OTP (cod greșit respins), dezactivare la final (stare curată).
