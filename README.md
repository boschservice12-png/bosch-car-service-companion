# Bosch Car Service Companion (BCSS)

The customer application and administration portal for SC Szkaliczki Service SRL.
User interface in three languages: **Romanian (default) · Hungarian · English**.

**Status: live pilot.** Deployed and serving at
[app.bcss.ro](https://app.bcss.ro), but not yet handed to end users. See
[Roadmap](docs/ROADMAP.md) for what stands between the pilot and real customers —
most importantly, [automated notifications do not exist yet](#not-implemented--automated-notifications).

---

## Product components

| Component | Path | Dev port | Production URL |
|---|---|---|---|
| Backend API (Symfony 7, PHP 8.3) | `backend/` | 8080 | internal only |
| Customer app (Next.js 15, installable PWA) | `apps/customer-web/` | 3000 | https://app.bcss.ro |
| Service/admin portal (Next.js 15) | `apps/service-admin/` | 3001 | https://admin.app.bcss.ro |

## What it does

**For the customer** (their own data only): vehicles, deadlines (roadworthiness
test / insurance / road tax / roadside assistance) with alerts and links to the
official checks (RAR, AIDA, eRovinieta), service history published by the
workshop (+ PDF), messages and requests, quote requests, roadside assistance
(two phone lines), mobility requests, taxes and duties (declarative records,
**no** document upload), and an "In case of an accident" flow to amiabila.com.

**Onboarding**: open registration with email and password. A vehicle created by
the workshop's Excel import is linked to an account with an **activation code**
issued by the workshop — single-use, hashed, expiring, with an attempt limit.
A registration plate or VIN alone **does not** grant access.

**For the workshop/admin**: a dashboard with three-field search (name / plate /
VIN), Excel/CSV import (customers + repair history, transactional and
idempotent), publishing and correcting history, inboxes (messages, quotes,
assistance, mobility, damage claims, taxes), deadline review, and **TOTP 2FA**
with backup codes.

**Security**: httpOnly sessions + CSRF double-submit on every state-changing
request, rate limiting (login/messages/upload), disabled accounts blocked
immediately, VIN unique at the database level, and full audit logging.

---

## Quick start

The fastest way to see the whole product running, with demo data:

```bash
docker compose -f compose.demo.yaml up --build
# Customer  http://localhost:3000
# Admin     http://localhost:3001
# Logins    admin@bcsc.ro / client@bcsc.ro   (password Demo1234!)
```

Native development (fastest iteration):

```bash
# 1) Backend — needs PHP 8.3+ and PostgreSQL via DATABASE_URL in backend/.env.local
cd backend && composer install \
  && php bin/console doctrine:migrations:migrate -n \
  && php -S 127.0.0.1:8080 -t public

# 2) Customer app
cd apps/customer-web && npm install && npm run dev

# 3) Admin portal (use a separate browser session)
cd apps/service-admin && npm install && npm run dev
```

Full details, including the Messenger worker the demo stack needs, are in
[docs/DEMO.md](docs/DEMO.md).

## Verification

```bash
./scripts/regression.sh     # everything: tests, lints, both frontend builds, compose validation
```

Or individually:

```bash
cd backend && php bin/console doctrine:schema:create --env=test && vendor/bin/phpunit
cd apps/customer-web  && npx tsc --noEmit && npx next build
cd apps/service-admin && npx tsc --noEmit && npx next build
cd e2e && npm install && npx playwright test    # needs the demo stack running
```

---

## Documentation

Start at the [documentation index](docs/README.md). The map:

| I want to… | Read |
|---|---|
| Understand how the system fits together | [Architecture](docs/ARCHITECTURE.md) |
| Run the system day to day | [Operations](docs/OPERATIONS.md) |
| Ship a change to production | [Deployment](docs/DEPLOYMENT.md) |
| Back up, restore, or recover from disaster | [Backup and restore](docs/BACKUP_AND_RESTORE.md) |
| Understand the alerting | [Monitoring](docs/MONITORING.md) |
| Debug something that is behaving oddly | [Troubleshooting](docs/TROUBLESHOOTING.md) |
| Know what is left to do | [Roadmap](docs/ROADMAP.md) |
| Call the API | [`docs/api/openapi.yaml`](docs/api/openapi.yaml) |
| Understand a past design decision | [`docs/architecture/adr/`](docs/architecture/adr/) |

Language convention: **all developer and operator documentation is in English.**
Two exceptions are deliberate — user-facing product strings, where Romanian is
the source language for the trilingual feature, and
[`docs/GHID_INSTALARE_COMPANION_RO.md`](docs/GHID_INSTALARE_COMPANION_RO.md),
which is written for Romanian customers rather than for developers.

---

## Not implemented — automated notifications

**The system sends no automated notifications.** There is no email, push, or
WhatsApp provider configured. Every notification — including the deadline
warnings for roadworthiness tests, insurance and road tax, which are the main
reason a customer installs the app — stops in the `MANUAL_ACTION_REQUIRED` state
and waits for **a human to send the message**.

What already exists:

- `NotificationDelivery` (`backend/src/Notification/`) is the contract, ready for
  a real implementation;
- `ManualNotificationDelivery` is the current implementation: it marks
  `MANUAL_ACTION_REQUIRED` / `SKIPPED`, never a blind `SENT`;
- `app:deadlines:scan` correctly computes what should be sent and when.

What is missing: a real provider (SMTP/SES/Postmark for email, a push provider
for the PWA) and a cost/GDPR decision about the channel.

**Whoever takes over the pilot has to know this before the first real users.**
A customer expecting an alert that their roadworthiness test is expiring will not
receive one automatically. Until this is built, the product's central promise is
delivered by hand.

## Note — the old demo

`archive/legacy-demo/` contains the original prototype (Vite/TanStack, data in
localStorage). **It is not the product** and must not be started or shipped; it
is kept only as a visual reference. `npm run dev` at the repository root
deliberately prints the instructions above instead of starting anything.
