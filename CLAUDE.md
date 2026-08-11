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

**Push to `main` deploys to production.** `.github/workflows/deploy.yml` runs the backend tests,
builds the four images on GitHub runners, pushes them to GHCR tagged with the commit SHA, then
SSHes to the server and runs `scripts/deploy-remote.sh`.

The four images are `bcsc-backend`, `bcsc-customer-web`, `bcsc-service-admin`, `bcsc-backup`
under `ghcr.io/boschservice12-png`. `worker`, `migrate`, and `scheduler` all reuse the backend
image. All four are rebuilt on every deploy even when only one changed — otherwise the current
SHA tag would not exist for the unchanged services and `docker compose pull` would fail on the
server. GHA layer caching keeps the unchanged ones cheap.

The server-side steps live in `scripts/deploy-remote.sh` rather than inline YAML so they can be
read, reviewed, and run by hand identically: `IMAGE_TAG=<sha> bash scripts/deploy-remote.sh`.
It refuses to run on a dirty working tree, backs up **before** deploying, pulls explicitly before
`up -d`, checks that `migrate` exited 0, and finishes by running `healthcheck.sh` — the same
definition of "healthy" the monitoring uses.

**Never build on the production box.** Two Next.js compiles plus the PHP extension build peg
both vCPUs for roughly 15 minutes while the same machine serves the pilot. This is why the deploy
script pulls explicitly with `set -e` before `up -d`: the services still carry a `build:` section,
so an `up` that cannot find an image would quietly start compiling on the server.

Rollback (code only, not schema):

```bash
cd /opt/bcss
git reset --hard <previous-sha>
IMAGE_TAG=<previous-sha> docker compose --env-file .env.prod -f compose.prod.yaml up -d
```

A failed deploy prints exactly this, with the real previous SHA filled in.

Schema problems require a restore from backup instead — the `migrate` service applies migrations
on every deploy, so a bad migration lands automatically. The deploy takes a backup immediately
before that happens; see `infrastructure/backup/restore.md`.

---

## 7. Conventions and traps

**Hand-written migrations must use Doctrine's FK index names** — `IDX_<crc32(table)><crc32(column)>`.
Otherwise `schema:validate` reports "not in sync" on PostgreSQL but passes on SQLite, because
SQLite builds the schema from mapping. This is the lesson of commit `6da4a4f`.

**Pin base images.** `FROM php:8-fpm` floated to PHP 8.5, where `pecl install redis` compiles but
installs no module, breaking the backend build entirely. Now pinned to `php:8.3-fpm`. Audit the
frontend Dockerfiles for the same pattern — unpinned `node:` tags will drift the same way.

**Migrations own the schema, not Messenger.** `MESSENGER_TRANSPORT_DSN` uses `auto_setup=0`
because `Version20260715234015` creates `messenger_messages`. Ordering is now enforced properly:
a one-shot `migrate` service owns migrations, and `backend` + `worker` both wait on
`service_completed_successfully`. `auto_setup=0` remains as defence in depth.

**Never use a literal hostname in `fastcgi_pass`.** nginx resolves it once at startup and caches
it forever. Any deploy that recreates `backend` gives it a new IP, and nginx keeps dialling the
old one — 502 across all of `/api` while the backend is perfectly healthy, until someone restarts
`api` by hand. Both frontends keep serving, so nothing looks wrong. `infrastructure/nginx/default.conf`
now puts the upstream in a variable with a `resolver`, forcing per-request resolution. This bit
production on 2026-08-11 and would have fired on every automated deploy.

**Bind-mounted files need `restart`, not `up -d`.** Compose only recreates a container when its
image or spec changes, so editing `infrastructure/nginx/default.conf` or any of the entrypoint
scripts in `infrastructure/docker/` has no effect until you `restart` that service. `up -d` will
cheerfully report `Running` and change nothing.

**`postgres:16` ships no CA certificates.** `ca-certificates` is not installed, so anything doing
HTTPS from that base image (here: `mc` pushing backups off-box) fails with
`x509: certificate signed by unknown authority`. Invisible against a plain-HTTP MinIO in testing;
only shows up against a real TLS endpoint. `infrastructure/backup/Dockerfile` installs it.

**The SQLite test database persists between runs** — tests must use unique keys per run.

**`schema:create` on SQLite degrades partial unique indexes into full ones.** Production
PostgreSQL, built via migrations, keeps the partial index.

---

## 8. Current production state

Verified working (2026-08-11): TLS on both hosts, all migrations applied (through
`Version20260721120000`), Messenger worker consuming from the `async` transport, and
`/api/health/ready` returning `ok` across database, migrations, messenger, storage, **scanner**,
and secrets. Backups run nightly and sync off-box to Lightsail bucket `backup-bcss`
(eu-central-1, object versioning enabled); restore verified from that bucket.

Production data is currently one admin user, zero vehicles, zero documents — the pilot has not
been handed to end users. Keep that in mind when a test "passes": most tables are empty.

Admin user `admin@bcss.ro` exists with role `SERVICE_ADMIN`. **2FA must be enrolled at first
login** — until then `/api/admin` routes are blocked by design.

Storage is MinIO in-stack (`STORAGE_DRIVER=s3`, `S3_ENDPOINT=http://minio:9000`). Real AWS S3
was considered and deferred: the `S3Storage` adapter has not been validated against live AWS,
and Lightsail offers no IAM instance roles, so switching now would mean embedding long-lived
keys with no tested code path. Migration later is an env-var change.

---

## 9. Open items

**Operational, before real users:**
- **Lightsail automatic snapshots** — not configured. Backups protect data; snapshots protect the
  machine. Rebuilding the instance is still a manual exercise.
- **`minio/minio` and `minio/mc` carry no tag**, so they resolve to `:latest`. The deploy no
  longer pulls third-party images, so they will not drift underneath you during a deploy, but an
  explicit pull would still surprise you. Pin them against whatever is currently running.
- **The alerting break test.** The dead-man's switch has only ever reported success. Until a
  failure has actually reached a human inbox, the alerting half is an assumption. Ten minutes:
  `docker compose stop api`, wait, confirm the email, start it again.
- RPO is 24h by construction (one backup at 03:00 UTC). Fine for now; confirm it is acceptable
  before real customer records accumulate.
- The restore drill passes, but production holds one user and no vehicles or documents, so it
  proves mechanism rather than behaviour at volume. The monthly drill re-checks this
  automatically as data grows.

*Closed 2026-08-11 (see `infrastructure/backup/restore.md` for the drill record):* off-box sync
to Lightsail object storage with read-back verification; `app:gdpr:purge` on a daily schedule via
the new `scheduler` service; restore exercised end-to-end including the disaster path
(`fetch-offsite.sh` → `restore.sh` from the bucket alone); dead-man's-switch monitoring on a
5-minute cron; **an automated monthly restore drill** (`scripts/restore-drill.sh`) so "restore
works" stays true instead of expiring the day after someone checked; `compose.prod.yaml` now
validated by `regression.sh`; the dead Caddy 8081 publish removed.

**Backups were empty for seven nights.** Between 2026-08-05 and 2026-08-11 every `db.sql.gz` was
20 bytes: `pg_dump` rejected the Doctrine DSN, and `pg_dump | gzip > f` returned gzip's exit
status so the failure was silent — `gzip -t` then certified the empty archive as intact. Fixed in
`f855587`. If you ever see a suspiciously small `db.sql.gz`, this is why.

**Deployment pipeline — built 2026-08-11, NOT yet exercised.** The workflow, the GHCR image
refs, and `scripts/deploy-remote.sh` are in place and the four images build with the exact
context/Dockerfile pairs the workflow uses. What has *not* happened is a real run: no push has
gone through it, the server has never pulled from GHCR, and the SSH path is untested. Before
relying on it, complete the one-time setup in `docs/DEPLOY_PILOT.md` §6.a — repo secrets and
`docker login ghcr.io` on the server — then watch the first deploy rather than assuming it works.

*Remaining one-time setup (see `docs/DEPLOY_PILOT.md` §6.a for the commands):*
- Repo secrets: `DEPLOY_HOST`, `DEPLOY_SSH_KEY`, `DEPLOY_KNOWN_HOSTS` (and optionally
  `DEPLOY_USER`, default `ubuntu`). Generate a dedicated deploy key, not a personal one.
- Server needs a one-time `docker login ghcr.io` with a `read:packages` PAT. Without it
  `docker compose pull` returns 401 and the deploy stops — correctly — before starting anything.
- Optional: a `production` environment in repo settings if you want manual approval on deploys.

*Already done:* GHCR `image:` refs on all four built services (`migrate`, `scheduler`, and
`worker` share the backend image); `.github/workflows/deploy.yml`; `scripts/deploy-remote.sh`.
Dockerfile paths confirmed — only **two** under `infrastructure/docker/`, `backend.Dockerfile`
(`php:8.3-fpm`) and a shared `frontend.Dockerfile` (`node:20-bookworm-slim`) used by both apps,
plus `infrastructure/backup/Dockerfile`. All bases pinned, so the unpinned-`node:` concern in §7
is closed.

**Product / code:**
- No automated notification provider. Everything stops at `MANUAL_ACTION_REQUIRED`; email is
  manual. `NotificationDelivery` is ready for a real implementation in `backend/src/Notification/`.
  Whoever owns the pilot needs to know this is the current state.
- `S3Storage` untested against live AWS. It *is* now exercised against in-stack MinIO on every
  readiness probe (`checks.storage` does a real write/read/delete), so the adapter works; what
  remains unproven is real AWS S3.
- Playwright e2e against the full stack (`e2e/README.md`).
- Caddy still publishes port 8081 from the no-domain default configuration. Harmless (the
  firewall blocks it) but dead config.

*Closed 2026-08-11:* ClamAV readiness check (`checks.scanner`, non-critical, verified live against
real ClamAV in production); `worker` ordering now enforced by the one-shot `migrate` service
rather than relying on `auto_setup=0`.

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
