# CLAUDE.md — Bosch Car Service Companion (BCSS)

Context for AI assistants working in this repo. Read this first.

---

## 1. What this is

A modular Symfony monolith plus two Next.js frontends, trilingual (RO default / HU / EN).
Customers track vehicle service deadlines and documents; service staff manage them through a
separate admin portal.

| Component | Directory | Stack | Dev port |
|---|---|---|---|
| API backend | `backend/` | Symfony 7, PHP 8.3, Doctrine, Messenger | 8080 |
| Customer app (PWA) | `apps/customer-web/` | Next.js 15 | 3000 |
| Service/admin portal | `apps/service-admin/` | Next.js 15 | 3001 |

Status: **live pilot**, deployed and serving. Not yet handed to end users.

---

## 2. Repository and branching

- Repo: `github.com/boschservice12-png/bosch-car-service-companion`
- **`main` is the production branch.** What is on `main` is what is (or is about to be) deployed.
- Historic work happened on `claude/pilot-readiness`; `main` was created from it at commit `d6ef37f`.
- Feature work branches off `main` and merges back via PR.

---

## 3. Local development

Native (fastest iteration):

```bash
# backend — needs PostgreSQL reachable via DATABASE_URL in backend/.env.local
cd backend && composer install \
  && php bin/console doctrine:migrations:migrate -n \
  && php -S 127.0.0.1:8080 -t public

cd apps/customer-web  && npm install && npm run dev
cd apps/service-admin && npm install && npm run dev
```

Full stack in Docker (closer to production, slower rebuilds):

```bash
docker compose -f compose.demo.yaml up --build
# customer http://localhost:3000 · admin http://localhost:3001
```

Run the demo stack periodically even when developing natively — environment drift between
host and container has already caused one production-only failure (see §7).

---

## 4. Tests

```bash
./scripts/regression.sh          # backend tests + lint + both frontend builds + compose validation

cd backend && php bin/console doctrine:schema:create --env=test && vendor/bin/phpunit
cd backend && php bin/console doctrine:migrations:migrate -n && php bin/console doctrine:schema:validate
```

Baseline at time of writing: 93/93 backend tests green, both frontend builds green,
migrations + `schema:validate` green against a real PostgreSQL 16.

---

## 5. Production environment

**Host:** AWS Lightsail, `eu-central-1` (Frankfurt), Ubuntu 24.04 LTS, OS-only blueprint.
8 GB RAM / 2 vCPU / 160 GB SSD. Static IP `54.93.39.7`. 4 GB swap at `/swapfile`.

Lightsail rather than EC2 was a deliberate cost decision for the pilot (~$49/mo vs ~$85/mo).
The trade-off accepted: no IAM instance roles, and vertical resize requires a snapshot rather
than a stop/start. Snapshots can be exported to EC2 if the pilot converts.

**Checkout:** `/opt/bcss`, cloned via a read-only GitHub deploy key.

**Domains:**

| Host | Serves |
|---|---|
| `app.bcss.ro` | customer PWA |
| `admin.app.bcss.ro` | service/admin portal |

DNS constraint worth knowing: the `bcss.ro` zone is hosted in cPanel elsewhere and its
nameservers **cannot** be changed. Both hostnames are plain A records added inside that zone.
Any future DNS change happens there, not in AWS.

Caddy obtains and renews Let's Encrypt certificates automatically for both hosts via
`CUSTOMER_SITE` / `ADMIN_SITE` in `.env.prod`.

**Firewall:** 22, 80, 443 only.

**Stack** (`compose.prod.yaml`): PostgreSQL 16 · Redis 7 · MinIO + minio-setup · ClamAV ·
backend (php-fpm, runs migrations on start) · worker (Messenger, same image as backend) ·
api (nginx) · customer-web · service-admin · caddy · backup.

**Secrets:** `.env.prod` lives only on the server, `chmod 600`, gitignored. It is the only copy —
there is no other source of truth for the generated credentials.

---

## 6. Deploying

Manual:

```bash
cd /opt/bcss
git pull
IMAGE_TAG=<sha> docker compose --env-file .env.prod -f compose.prod.yaml pull
docker compose --env-file .env.prod -f compose.prod.yaml up -d
```

Automated (in progress — see §9): push to `main` triggers GitHub Actions, which builds the four
images on GitHub runners, pushes them to GHCR tagged with the commit SHA, then SSHes to the
server to pull, run a one-shot backup, and restart.

**Never build on the production box.** Two Next.js compiles plus the PHP extension build peg
both vCPUs for roughly 15 minutes while the same machine serves the pilot.

Rollback (code only, not schema):

```bash
IMAGE_TAG=<previous-sha> docker compose --env-file .env.prod -f compose.prod.yaml up -d
```

Schema problems require a restore from backup instead — the backend entrypoint runs
`doctrine:migrations:migrate` on every start, so a bad migration is applied automatically.

---

## 7. Conventions and traps

**Hand-written migrations must use Doctrine's FK index names** — `IDX_<crc32(table)><crc32(column)>`.
Otherwise `schema:validate` reports "not in sync" on PostgreSQL but passes on SQLite, because
SQLite builds the schema from mapping. This is the lesson of commit `6da4a4f`.

**Pin base images.** `FROM php:8-fpm` floated to PHP 8.5, where `pecl install redis` compiles but
installs no module, breaking the backend build entirely. Now pinned to `php:8.3-fpm`. Audit the
frontend Dockerfiles for the same pattern — unpinned `node:` tags will drift the same way.

**Migrations own the schema, not Messenger.** `MESSENGER_TRANSPORT_DSN` must use `auto_setup=0`.
With `auto_setup=1` the worker and the backend start simultaneously, the worker creates
`messenger_messages` first, and the backend's migration then fails on a duplicate table. This is
a race, so it fails non-deterministically and did not show up in CI. The DSN change is a
workaround; the proper fix is an ordering dependency so `worker` waits for migrations to finish.

**The SQLite test database persists between runs** — tests must use unique keys per run.

**`schema:create` on SQLite degrades partial unique indexes into full ones.** Production
PostgreSQL, built via migrations, keeps the partial index.

---

## 8. Current production state

Verified working: TLS on both hosts, all migrations applied (through
`Version20260721120000`), Messenger worker consuming from the `async` transport, and
`/api/health/ready` returning `ok` across database, migrations, messenger, storage, and secrets.

Admin user `admin@bcss.ro` exists with role `SERVICE_ADMIN`. **2FA must be enrolled at first
login** — until then `/api/admin` routes are blocked by design.

Storage is MinIO in-stack (`STORAGE_DRIVER=s3`, `S3_ENDPOINT=http://minio:9000`). Real AWS S3
was considered and deferred: the `S3Storage` adapter has not been validated against live AWS,
and Lightsail offers no IAM instance roles, so switching now would mean embedding long-lived
keys with no tested code path. Migration later is an env-var change.

---

## 9. Open items

**Operational, before real users:**
- Backups are written to a volume on the same disk. They must sync **off-box** (S3) — a backup on
  the machine it protects is not a backup.
- `app:gdpr:purge` needs a daily cron. It does not run itself, and this system holds customer
  vehicle records.
- Restore has never been exercised. Run `infrastructure/backup/restore.sh` in isolation and record
  actual RTO/RPO.
- Lightsail automatic snapshots.

**Deployment pipeline (partly built):**
- `image:` entries pointing at `ghcr.io/boschservice12-png/bcsc-<service>:${IMAGE_TAG:-latest}`
  need adding to the four built services in `compose.prod.yaml`, alongside the existing `build:`.
  `worker` reuses the backend image.
- `.github/workflows/deploy.yml` — build matrix pushing to GHCR, then SSH deploy.
- Repo secrets: `DEPLOY_SSH_KEY`, `DEPLOY_HOST`, `DEPLOY_KNOWN_HOSTS`.
- Server needs a one-time `docker login ghcr.io` with a `read:packages` PAT.
- Verify the four Dockerfile paths under `infrastructure/docker/` — only
  `backend.Dockerfile` has been confirmed.

**Product / code:**
- No automated notification provider. Everything stops at `MANUAL_ACTION_REQUIRED`; email is
  manual. `NotificationDelivery` is ready for a real implementation in `backend/src/Notification/`.
  Whoever owns the pilot needs to know this is the current state.
- `S3Storage` untested against live MinIO/AWS.
- Playwright e2e against the full stack (`e2e/README.md`).
- ClamAV is **not** part of `/api/health/ready`. If the scanner dies, readiness stays green while
  document processing silently stalls. Worth adding as a non-critical check.
- Caddy still publishes port 8081 from the no-domain default configuration. Harmless (the
  firewall blocks it) but dead config.
- Fix the `worker` ordering dependency properly rather than relying on `auto_setup=0`.

---

## 10. Key documents

| Subject | File |
|---|---|
| Pilot operation (6 blocks, env, readiness, backup) | `docs/PILOT_READINESS.md` |
| Production deploy runbook | `docs/DEPLOY_PILOT.md` |
| API contract (kept in sync with the router, enforced by test) | `docs/api/openapi.yaml` |
| Demo run | `docs/DEMO.md` |
| Backup + restore | `infrastructure/backup/` |
| Architecture decisions | `docs/architecture/` |

Note: ADR-0004 claims the PHP version is pinned. Until commit `d6ef37f` the Dockerfile did not
actually pin it. If other ADRs assert constraints, verify they are enforced rather than assumed.
