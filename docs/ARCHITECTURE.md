# Architecture

How the system is put together, and why. For operating it day to day see
[Operations](OPERATIONS.md); for shipping changes see [Deployment](DEPLOYMENT.md).

---

## 1. Shape of the system

A **modular monolith** (Symfony 7, PHP 8.3) behind two separate Next.js 15
frontends. One database, one deployable backend, clear module boundaries inside
it.

```
                    ┌──────────────────────── Caddy ────────────────────────┐
   browser ────────▶│  TLS termination, Let's Encrypt, :80 → :443 redirect   │
                    └───────┬───────────────────────────────┬───────────────┘
                            │ app.bcss.ro                   │ admin.app.bcss.ro
                    ┌───────▼────────┐              ┌───────▼────────┐
                    │  customer-web  │              │ service-admin  │
                    │  Next.js :3000 │              │ Next.js :3001  │
                    └───────┬────────┘              └───────┬────────┘
                            │  /api/* rewritten server-side │
                            └───────────────┬───────────────┘
                                    ┌───────▼───────┐
                                    │  api (nginx)  │
                                    └───────┬───────┘
                                            │ FastCGI
                                    ┌───────▼───────┐
                                    │    backend    │
                                    │  php-fpm      │
                                    └───┬───┬───┬───┘
                    ┌───────────────────┘   │   └──────────────────┐
              ┌─────▼─────┐          ┌──────▼──────┐        ┌──────▼──────┐
              │ PostgreSQL│          │ MinIO (S3)  │        │   ClamAV    │
              │   :5432   │          │   :9000     │        │   :3310     │
              └─────▲─────┘          └──────▲──────┘        └──────▲──────┘
                    │                       │                      │
              ┌─────┴───────────────────────┴──────────────────────┴─────┐
              │  worker (Messenger)   ·   scheduler (GDPR)   ·   backup   │
              └───────────────────────────────────────────────────────────┘
```

**The browser never talks to the backend directly.** It talks to a Next.js
server, which rewrites `/api/*` onward to nginx server-side. Two consequences
worth understanding:

- CORS is not needed, and cookies stay same-origin — which is what makes
  httpOnly session cookies plus CSRF double-submit workable.
- The backend has no public hostname. It is only reachable inside the Docker
  network, as `http://api`.

## 2. Backend modules

Each module under `backend/src/` follows the same four-layer structure:

| Layer | Contains |
|---|---|
| `Domain/` | Entities, value objects, repository *interfaces*. No framework code. |
| `Application/` | Use cases, services, Messenger messages and handlers. |
| `Infrastructure/` | Doctrine repositories, S3/ClamAV adapters — the interface implementations. |
| `Presentation/` | HTTP controllers, serializers, request DTOs, console commands. |

The modules:

| Module | Responsibility |
|---|---|
| `Identity` | Authentication, users, TOTP 2FA, account state |
| `Customer` | Customer profiles, consents, GDPR retention |
| `Vehicle` | Vehicles, ownership, activation tokens |
| `Deadline` | Roadworthiness/insurance/road-tax deadlines and their status |
| `Document` | Uploads, private storage, antimalware scanning, signed URLs |
| `ServiceHistory` | Repair history published by the workshop |
| `Communication` | Conversations and messages |
| `QuoteRequest` | Quote requests and workshop responses |
| `Roadside` | Roadside assistance requests |
| `Mobility` | Replacement-vehicle requests |
| `DamageClaim` | Damage claim files |
| `Tax` | Taxes and duties (declarative records) |
| `Notification` | Notification model and delivery contract |
| `Audit` | Audit log of sensitive operations |
| `Settings` | Application settings, demo seed |
| `System` | Health and readiness checks |
| `Shared` | CORS, CSRF, rate limiting, exception handling |

Wiring lives in `backend/config/services.yaml`, which binds each `Domain`
interface to its `Infrastructure` implementation. Two bindings change by
environment:

- `MalwareScanner` → `PermissiveDevScanner` in dev, `ClamAvScanner` in prod
- `StorageAdapter` → chosen at runtime by `STORAGE_DRIVER` (`local` or `s3`)

## 3. Data model

26 tables, 20 migrations. The core:

```
users ──┬── customer_profiles ── consents
        │
        └── vehicle_ownerships ── vehicles ──┬── vehicle_deadlines ── deadline_notifications
                                             ├── service_records ── service_record_documents
                                             ├── documents
                                             ├── tax_items
                                             ├── quote_requests ── quote_responses
                                             ├── roadside_requests ── roadside_request_documents
                                             ├── mobility_requests
                                             └── damage_claims ── damage_claim_documents

conversations ── messages ── message_attachments
service_admins          (workshop staff, separate from customer users)
vehicle_activation_tokens
audit_logs · notifications · application_settings
```

Full entity-relationship detail: [`data-model/erd.md`](data-model/erd.md).

**Migrations own the schema.** Hand-written migrations must use Doctrine's
foreign-key index naming, `IDX_<crc32(table)><crc32(column)>` — otherwise
`schema:validate` reports "not in sync" on PostgreSQL while passing on SQLite.
See [Troubleshooting](TROUBLESHOOTING.md).

## 4. Request lifecycle

1. Caddy terminates TLS and routes by hostname to one of the two Next.js apps.
2. Next.js serves the page. Client-side calls go to `/api/*` on the same origin.
3. The Next.js server rewrites `/api/*` to `http://api` (nginx) inside the network.
4. nginx passes it to `backend:9000` over FastCGI.
   - The upstream is resolved **per request** via a variable plus Docker's
     internal resolver. With a literal hostname nginx caches the IP forever and
     every deploy that recreates the backend produces a 502. See
     [Troubleshooting](TROUBLESHOOTING.md).
5. Symfony authenticates the session cookie, checks CSRF on state-changing
   requests, applies rate limits, and dispatches to the module controller.
6. Slow or risky work (document scanning, notifications) is pushed to Messenger's
   `async` transport rather than done in the request.

## 5. Asynchronous work

The `worker` service consumes the `async` transport. Without it, uploaded
documents stay `PENDING` forever.

- Transport: Doctrine (`messenger_messages` table), **not** Redis.
- `MESSENGER_TRANSPORT_DSN` uses `auto_setup=0`: the table is created by
  migration `Version20260715234015`, not by Messenger.
- Failed messages land in the `failed` transport after `max_retries`; inspect
  with `php bin/console messenger:failed:show`.

Redis is deployed and available but currently used for caching and rate limiting
rather than as the queue transport.

## 6. Start-up ordering

This is deliberate and load-bearing:

```
db (healthy) ──▶ migrate (one-shot, exits 0) ──┬──▶ backend ──▶ api
                                               ├──▶ worker
                                               └──▶ scheduler
minio ──▶ minio-setup (one-shot, exits 0) ─────┘
```

`migrate` is the **single owner of the schema**. Both `backend` and `worker`
wait on `service_completed_successfully`, so the worker can never race the
migration that creates `messenger_messages`. The worker depends on `migrate`
rather than on `backend` because it needs the *schema*, not php-fpm — so it
starts in parallel with the backend but never before the migrations.

If `migrate` exits non-zero, `backend` and `worker` **do not start at all**. A
bad migration therefore fails visibly instead of leaving services running against
a half-migrated schema.

## 7. Storage

Documents are private and never served directly. `STORAGE_DRIVER` selects the
adapter:

- `local` — `backend/var/storage`, used in dev and the demo
- `s3` — MinIO in-stack in production (`S3_ENDPOINT=http://minio:9000`)

Downloads go through short-lived signed URLs generated by the application.
Uploads are scanned by ClamAV before becoming servable — the scanner is
**fail-closed**: if the daemon is unreachable, the upload is rejected rather than
accepted unscanned.

Real AWS S3 was considered and deferred: the `S3Storage` adapter has not been
validated against live AWS, and Lightsail offers no IAM instance roles, so
switching now would mean embedding long-lived keys against an untested code path.
Migration later is an environment-variable change.

## 8. Authentication

- Customers: email + password, `json_login`, httpOnly session cookie,
  stateless disabled. Login throttled to 5 attempts.
- Workshop staff (`service_admins`): the same login plus **mandatory TOTP 2FA**.
  In production, `/api/admin` routes are blocked for an admin who has not
  enrolled — enforced by `TwoFactorGuardListener` and the
  `admin_2fa_enforce_enrollment` parameter under `when@prod`.
- Disabled accounts are rejected at authentication time by `ActiveUserChecker`.
- Every state-changing request requires a CSRF double-submit token.

## 9. Internationalisation

The UI is trilingual, and the implementation matters when editing code:

**Romanian is the source and key language.** `apps/*/lib/i18n.tsx` holds
dictionaries that translate *from* Romanian into Hungarian and English:

```tsx
const HU: Dict = { 'Acasă': 'Kezdőlap', … };
const EN: Dict = { 'Acasă': 'Home', … };
```

Components call `t('Taxe și impozite')`. A missing translation falls back to
Romanian, so the app never breaks — it just shows Romanian.

Backend-produced labels (`statusLabel` and similar) are Romanian by design and
are translated client-side through the same dictionaries.

**Consequence:** Romanian strings in `.tsx` components and in backend label
enums are *not* untranslated text to be tidied up. They are dictionary keys.
Changing them silently breaks Hungarian and English. Developer-facing text —
comments, docs, script output — is English throughout.

## 10. Environments

| | Demo / local | Production |
|---|---|---|
| File | `compose.demo.yaml` | `compose.prod.yaml` |
| `APP_ENV` | `dev` | `prod` |
| Storage | local disk | MinIO (S3) |
| Antimalware | permissive stub | ClamAV, fail-closed |
| TLS | none | Caddy + Let's Encrypt |
| Migrations | backend entrypoint | one-shot `migrate` service |
| Seed data | `app:demo:seed` | none |

The demo stack is deliberately not a production replica. Run it periodically
anyway — drift between host and container has already caused one
production-only failure.

## 11. Production host

AWS Lightsail, `eu-central-1` (Frankfurt), Ubuntu 24.04, 8 GB RAM / 2 vCPU /
160 GB SSD, static IP `54.93.39.7`, 4 GB swap. Checkout at `/opt/bcss` via a
read-only GitHub deploy key. Firewall: 22, 80, 443 only.

Lightsail rather than EC2 was a deliberate cost decision for the pilot
(~$49/mo vs ~$85/mo). The trade-offs accepted: no IAM instance roles, and
vertical resize requires a snapshot rather than a stop/start. Snapshots can be
exported to EC2 if the pilot converts.

DNS constraint: the `bcss.ro` zone is hosted in cPanel elsewhere and its
nameservers **cannot** be changed. Both hostnames are plain A records in that
zone. Any future DNS change happens there, not in AWS.
