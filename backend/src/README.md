# Domain modules (modular monolith)

Every module has the layers `Domain/`, `Application/`, `Infrastructure/`,
`Presentation/`. Modules communicate **only through public contracts and
interfaces** — never by reaching into another module's internal classes. There
is no `Utils` folder.

See [ADR 0001](../../docs/architecture/adr/0001-modular-monolith.md) for the
reasoning and [Architecture §2](../../docs/ARCHITECTURE.md) for how this fits
the wider system.

| Module | Responsibility |
|---|---|
| `Identity` | Email + password authentication, sessions, registration, TOTP 2FA for staff |
| `Customer` | Customer profile, consents, GDPR rights and retention |
| `Vehicle` | Vehicles, VIN, ownership, activation tokens, object-level authorisation |
| `Communication` | Conversations, messages, attachments |
| `Deadline` | Roadworthiness/insurance/road-tax/roadside deadlines, status calculation, threshold notifications |
| `ServiceHistory` | Service history, corrections, documents |
| `QuoteRequest` | Quote requests and workshop responses, with a state machine |
| `Roadside` | Roadside assistance requests (forwarding = internal + telephone) |
| `Mobility` | Replacement-vehicle requests |
| `DamageClaim` | Damage claim files (data collection) |
| `Tax` | Annual taxes and duties (declarative records) |
| `Document` | Secure upload, MIME + extension + size validation, malware scanning, private storage, signed temporary URLs |
| `Notification` | Notification entity and the delivery contract (Messenger async) |
| `Audit` | Before/after audit log (`AuditRecorder`) |
| `Settings` | `application_settings` (thresholds, texts, WhatsApp, upload limits), demo seed |
| `Shared` | Standard error shape, exception listener, CORS, CSRF, rate limiting |
| `System` | Health endpoints (`/api/health`, `/api/health/ready`) |

`Controller/`, `Entity/` and `Repository/` at the top level are empty leftovers
from the Symfony skeleton. Nothing lives there; do not add to them.

## API surface

The full contract is in [`docs/api/openapi.yaml`](../../docs/api/openapi.yaml),
which is kept in sync with the router and enforced by `OpenApiSyncTest`. Route
groups:

| Prefix | Purpose |
|---|---|
| `/api/auth/*` | Registration, login, logout |
| `/api/me`, `/api/me/export`, `/api/me/delete` | Current user, GDPR export and erasure |
| `/api/vehicles/*` | Customer's vehicles |
| `/api/deadlines/*` | Deadlines and their status |
| `/api/documents/*` | Upload, signed download URL, raw serving |
| `/api/service-records/*` | Published service history |
| `/api/conversations/*` | Messages |
| `/api/quote-requests/*` | Quote requests |
| `/api/roadside-requests/*` | Roadside assistance |
| `/api/mobility-requests/*` | Mobility requests |
| `/api/taxes/*` | Taxes and duties |
| `/api/settings` | Public settings |
| `/api/csrf` | CSRF token issuance |
| `/api/health`, `/api/health/ready` | Liveness and readiness |
| `/api/admin/*` | Workshop portal (16 route groups; requires SERVICE_ADMIN **and** enrolled 2FA in production) |

## Running and verification

```bash
# Dependencies
composer install

# Database + schema (PostgreSQL)
php bin/console doctrine:migrations:migrate --no-interaction

# Demo data (idempotent): admin@bcsc.ro / client@bcsc.ro, password Demo1234!
php bin/console app:demo:seed

# Individual users
php bin/console app:user:create client@example.ro Password123          # customer
php bin/console app:user:create admin@example.ro Password123 --admin   # admin

# Tests (the test environment uses SQLite by default — see .env.test)
php bin/console doctrine:schema:create --env=test
vendor/bin/phpunit

# Validation
php bin/console lint:container
php bin/console doctrine:schema:validate
```

Two testing caveats worth knowing before trusting a green run:

- **The SQLite test database persists between runs** — tests must use unique keys
  per run.
- **`schema:create` on SQLite degrades partial unique indexes into full ones**,
  and SQLite builds the schema from the mapping rather than from migrations. Some
  schema mistakes are therefore only visible against PostgreSQL. See
  [Troubleshooting §10](../../docs/TROUBLESHOOTING.md).

The domain code is **pure PHP** with no framework dependencies, covered by unit
tests in `tests/Unit/` (deadline calculation, state transitions, VIN validation).
