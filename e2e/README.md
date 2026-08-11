# Browser end-to-end tests (CUSTOMER + ADMIN)

Playwright tests that drive the real flow through the UI from **two sessions**
(customer and workshop), against demo data. They complement the backend
functional tests in `backend/tests/Functional`, which already run in CI.

> These tests are **not** part of CI — they need the full stack running. Run them
> locally before a demo, or add them to a dedicated workflow. Getting them into
> CI is on the [roadmap](../docs/ROADMAP.md).

## Prerequisites

The stack running with demo data loaded (see [`../docs/DEMO.md`](../docs/DEMO.md)).
A quick recipe without Docker (local PostgreSQL + the PHP built-in server):

```bash
# 1) Database: backend/.env.local with DATABASE_URL pointing at local PostgreSQL, then:
cd backend
php bin/console doctrine:migrations:migrate -n
php bin/console app:demo:seed          # idempotent
php -S 127.0.0.1:8080 -t public public/index.php &

# 2) The applications (build once, then start):
cd ../apps/customer-web  && npx next build && npx next start -p 3000 &
cd ../service-admin      && npx next build && npx next start -p 3001 &
```

## Running

```bash
cd e2e
npm install
npx playwright test
# or with custom URLs:
CLIENT_URL=http://localhost:3000 ADMIN_URL=http://localhost:3001 npx playwright test
```

- On machines that already have a Chromium of a different version than Playwright
  expects, do NOT run `playwright install` — point at the executable instead:
  `CHROMIUM_PATH=/opt/pw-browsers/chromium npx playwright test`.
- `npx playwright test --list` lists the tests without starting a browser.

## What they cover (P1-08)

- **client-admin** — the demo flow: the CUSTOMER sees their vehicles (`MS01POP`),
  deadlines, published history and the demo conversation; the ADMIN sees customer
  vehicles, conversations, and the quote request in the quotes inbox.
- **client-flows** — end to end on a NEW account: open registration → own vehicle
  → tax → declarative partial payment (no files) → the RO→HU language switch
  (applied immediately and persisted across a reload). Plus messaging in both
  directions: the customer writes, the admin replies, the customer sees the reply
  (two separate browser contexts).
- **admin-flows** — the dashboard's three-field search (name / plate / VIN,
  normalised and combined with AND) and module navigation (taxes, security).
- **two-factor** — P0-06 through the real interface: 2FA enrolment (password →
  secret → a TOTP code computed in the test per RFC 6238 → backup codes),
  re-login with the OTP challenge (a wrong code is rejected), and disabling it at
  the end so the state stays clean.
