# E2E de browser (CLIENT + ADMIN)

Teste Playwright care conduc fluxul real prin UI, din **două sesiuni** (client și
service), împotriva datelor demo. Complementare testelor funcționale din backend
(`backend/tests/Functional`), care rulează deja în CI.

> Aceste teste **nu** fac parte (încă) din CI — necesită stiva completă pornită.
> Rulați-le local înainte de un demo, sau adăugați-le într-un workflow dedicat.

## Precondiții

Stiva pornită și datele demo încărcate (vezi `docs/DEMO.md`):

```bash
# backend :8080 + app:demo:seed
# customer-web :3000
# service-admin :3001
```

## Rulare

```bash
cd e2e
npm install
npx playwright test
# sau, cu URL-uri personalizate:
CLIENT_URL=http://localhost:3000 ADMIN_URL=http://localhost:3001 npx playwright test
```

- În mediile unde Chromium este preinstalat (`PLAYWRIGHT_BROWSERS_PATH`), nu rulați
  `playwright install` — testele folosesc browserul existent.
- `npx playwright test --list` listează testele fără a porni browserul (verificare rapidă).

## Ce acoperă

- **CLIENT**: login → vehicule (`MS01POP`) → scadențe → istoric service → cererea de ofertă demo.
- **ADMIN**: login → vehiculele clienților → conversații (cererea de ofertă).
