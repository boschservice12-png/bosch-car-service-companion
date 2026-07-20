# Bosch Car Service Companion

Aplicația clienților și portalul de administrare pentru SC Szkaliczki Service SRL.
Interfață în 3 limbi: **română (implicită) · maghiară · engleză**.

## Componentele produsului

| Componentă | Cale | Port dev |
|---|---|---|
| Backend API (Symfony) | `backend/` | 8080 |
| Aplicația clientului (Next.js, PWA instalabilă) | `apps/customer-web/` | 3000 |
| Portal service/admin (Next.js) | `apps/service-admin/` | 3001 |

## Funcționalități principale

- **Client** (doar datele proprii): vehicule, scadențe (ITP / RCA / taxă de drum /
  asistență rutieră) cu alerte și linkuri de verificare oficială (RAR, AIDA,
  eRovinieta), istoric service publicat de service (+ PDF), mesaje și cereri,
  cereri de ofertă, asistență rutieră (două linii telefonice), mobilitate,
  taxe și impozite (evidență declarativă, **fără** încărcare de documente),
  „În caz de accident" → amiabila.com.
- **Onboarding**: înregistrare liberă cu email + parolă; un cont creat de
  importul Excel al service-ului se revendică la înregistrare cu **numărul de
  înmatriculare** drept dovadă — clientul își vede imediat vehiculele și istoricul.
- **Service/admin**: panou cu căutare pe 3 câmpuri (nume / număr / VIN),
  import Excel/CSV (clienți + istoric reparații, tranzacțional și idempotent),
  publicare/corecție istoric, inbox-uri (mesaje, oferte, asistență, mobilitate,
  daune, taxe), verificarea scadențelor, **2FA TOTP** cu coduri de rezervă.
- **Securitate**: sesiuni httpOnly + CSRF double-submit pe toate cererile
  modificatoare, rate limiting (login/mesaje/upload), conturi dezactivate
  blocate imediat, VIN unic la nivel de bază de date, audit complet.

## Pornire (dezvoltare)

```bash
# 1) Backend (necesită PHP 8.2+ și PostgreSQL prin DATABASE_URL / .env.local)
cd backend && composer install \
  && php bin/console doctrine:migrations:migrate -n \
  && php -S 127.0.0.1:8080 -t public

# 2) Aplicația clientului
cd apps/customer-web && npm install && npm run dev

# 3) Portalul admin (sesiune separată de browser)
cd apps/service-admin && npm install && npm run dev
```

Alternativ, toată stiva cu date demo: `docker compose -f compose.demo.yaml up --build`
(client: http://localhost:3000 · admin: http://localhost:3001). Detalii: `docs/DEMO.md`.

## Verificare

```bash
# Backend: unit + funcțional (include gardianul OpenAPI ↔ router)
cd backend && php bin/console doctrine:schema:create --env=test && vendor/bin/phpunit

# Frontenduri
cd apps/customer-web  && npx tsc --noEmit && npx next build
cd apps/service-admin && npx tsc --noEmit && npx next build

# E2E de browser pe stiva pornită cu date demo (vezi e2e/README.md)
cd e2e && npm install && npx playwright test
```

## Documentație

| Subiect | Unde |
|---|---|
| Contract API (sincron cu routerul, impus de `OpenApiSyncTest`) | `docs/api/openapi.yaml` |
| Rulare demo + date demo | `docs/DEMO.md` |
| Backup + restaurare (drill lunar) | `infrastructure/backup/` |
| Monitorizare, cron, semnale de urmărit | `infrastructure/monitoring/monitoring.md` |
| Teste e2e de browser | `e2e/README.md` |
| Decizii de arhitectură | `docs/architecture/` (inclusiv `adr/`) |

## Atenție — demo-ul vechi

`archive/legacy-demo/` conține prototipul inițial (Vite/TanStack, date stocate
în localStorage). **Nu este produsul** și nu trebuie pornit sau livrat; este
păstrat doar ca referință vizuală. `npm run dev` din rădăcină afișează
intenționat instrucțiunile de mai sus în loc să pornească ceva.
