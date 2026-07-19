# Bosch Car Service Companion

Aplicația clienților și portalul de administrare pentru SC Szkaliczki Service SRL.
Interfață în 3 limbi: **română (implicită) · maghiară · engleză**.

## Componentele produsului

| Componentă | Cale | Port dev |
|---|---|---|
| Backend API (Symfony) | `backend/` | 8080 |
| Aplicația clientului (Next.js) | `apps/customer-web/` | 3000 |
| Portal service/admin (Next.js) | `apps/service-admin/` | 3001 |

## Pornire (dezvoltare)

```bash
# 1) Backend (necesită PHP 8.2+ și PostgreSQL prin DATABASE_URL)
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
cd backend && php bin/console doctrine:schema:create --env=test && vendor/bin/phpunit
cd apps/customer-web && npx tsc --noEmit && npx next build
cd apps/service-admin && npx tsc --noEmit && npx next build
```

## Atenție — demo-ul vechi

`archive/legacy-demo/` conține prototipul inițial (Vite/TanStack, date stocate
în localStorage). **Nu este produsul** și nu trebuie pornit sau livrat; este
păstrat doar ca referință vizuală. `npm run dev` din rădăcină afișează
intenționat instrucțiunile de mai sus în loc să pornească ceva.
