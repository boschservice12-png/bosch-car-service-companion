# Environments

## The three environments

| Environment | Purpose | Notes |
|---|---|---|
| **local** | Development | `compose.demo.yaml` or native; MinIO, no real OTP/email transports |
| **test** | Automated tests | SQLite by default (see `backend/.env.test`); PostgreSQL in CI for the migration check |
| **production** | The live pilot | `compose.prod.yaml`; secrets in `.env.prod` on the server; daily backups; monitoring |

There is currently **no staging environment**. Changes go from a feature branch,
through CI, to production. For a single-workshop pilot this is a deliberate
trade-off; it is worth revisiting before significant user load.

## CI/CD

Four workflows in `.github/workflows/`:

| Workflow | Trigger | Does |
|---|---|---|
| `backend-ci.yml` | push/PR touching `backend/**` | PHPUnit on SQLite, container lint, migration + `schema:validate` against real PostgreSQL |
| `customer-web-ci.yml` | push/PR touching `apps/customer-web/**` | typecheck, lint, build |
| `service-admin-ci.yml` | push/PR touching `apps/service-admin/**` | typecheck, lint, build |
| `deploy.yml` | push to `main` | test → build four images to GHCR → SSH deploy |

The first three are **path-filtered**, so they do not run on every push to
`main`. That is why `deploy.yml` carries its own test gate rather than depending
on them. Full detail: [Deployment](../../docs/DEPLOYMENT.md).

## Rules

- No secrets in the repository. `.env` files are local only; production secrets
  live in `/opt/bcss/.env.prod` (chmod 600), which is the only copy.
- Every PR passes lint and tests before merge.
- **Migrations run automatically on deploy**, via the one-shot `migrate` service.
  A failed migration prevents `backend` and `worker` from starting at all, so a
  bad migration fails visibly rather than half-applying. The deploy takes a
  backup immediately beforehand.

## Pilot configuration notes

- **Storage:** `STORAGE_DRIVER=local` (dev/demo, persistent volume) or `s3`
  (production, with `S3_ENDPOINT` / `S3_BUCKET` / `S3_KEY` / `S3_SECRET` /
  `S3_REGION`).
- **Readiness vs liveness:** an orchestrator should use `GET /api/health/ready`
  (deep — database, migrations, storage, secrets → `503` when a critical
  dependency is down) for rotation, and `GET /api/health` (pure liveness) for
  restarts. Do not tie restarts to readiness.
- **`APP_SECRET` must be a real value** — readiness fails on a default or one
  containing "change".
- **The Messenger worker** runs as a separate service (`messenger:consume async`);
  without it documents stay `PENDING`.
- **`LEGACY_PLATE_CLAIM_ENABLED=false`** — vehicle access requires an activation
  code, never a plate alone.
