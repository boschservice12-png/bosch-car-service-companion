# Troubleshooting and known traps

Every entry here is a defect that actually occurred, with the mechanism and the
fix. They share a theme worth stating up front:

> **Every one of these reported success while doing nothing.** `gzip -t`
> validating an empty archive, a pipeline returning the wrong exit code,
> readiness green with a dead scanner, both frontends serving while the API was
> down, a deploy pipeline reporting success without deploying. None of them
> failed loudly. That is why the fixes are mostly *verification* rather than
> logic, and why alerting is tested by breaking things.

---

## 1. `/api` returns 502 but both frontends work

**Symptom.** app.bcss.ro and admin.app.bcss.ro load normally. Every `/api/*` call
returns 502. The backend container is healthy and its log shows
`fpm is running, ready to handle connections`.

nginx logs show:

```
connect() failed (111: Connection refused) while connecting to upstream,
upstream: "fastcgi://172.18.0.4:9000"
```

**Mechanism.** nginx resolves a literal hostname in `fastcgi_pass` **once**, at
worker start-up, and caches it for the life of the process. Any deploy that
recreates the backend container gives it a new IP; nginx keeps dialling the old
one.

**Immediate fix.**

```bash
docker compose --env-file .env.prod -f compose.prod.yaml restart api
```

**Permanent fix** (already applied). `infrastructure/nginx/default.conf` puts the
upstream in a variable plus a `resolver`, forcing per-request resolution:

```nginx
resolver 127.0.0.11 valid=10s ipv6=off;
set $bcss_backend backend:9000;
fastcgi_pass $bcss_backend;
```

Do not revert to `fastcgi_pass backend:9000;`. Occurred 2026-08-11; it would
otherwise fire on every automated deploy.

---

## 2. Backups are empty (20-byte `db.sql.gz`)

**Symptom.** Backup directories exist on schedule, but `db.sql.gz` is 20 bytes.
The backup log reports success.

**Mechanism — two defects compounding.**

1. `pg_dump` rejects the `DATABASE_URL` from `.env.prod.example`:
   `invalid URI query parameter: "serverVersion"`. `serverVersion` and `charset`
   are Doctrine parameters, not libpq ones.
2. The failure was invisible: `pg_dump … | gzip > file` returns **gzip's** exit
   status, always 0. The result is an empty but valid gzip — which `gzip -t`, the
   integrity check, happily certifies.

**Fix** (applied). A `pg_dsn()` helper strips only the Doctrine-only parameters,
preserving everything else including `sslmode`. The dump is a separate step with
its own exit code, plus content assertions (the `PostgreSQL database dump
complete` marker and the presence of `CREATE TABLE`/`COPY`).
`healthcheck.sh` also asserts a minimum dump size.

**If you see it again:** `ls -lh /var/lib/docker/volumes/bcsc-prod_backups/_data/*/db.sql.gz`.
Anything around 20 bytes is empty. Occurred 2026-08-05 to 2026-08-11.

---

## 3. Restore silently loses every document

**Symptom.** A restore completes with exit 0. The database is back; no documents are.

**Mechanism.** `backup-cron.sh` writes `documents.tar.gz`; `restore.sh` looked for
`storage.tar.gz`, did not find it, printed a warning, and exited **0**.

**Fix** (applied). `restore.sh` accepts both layouts, restores documents back into
the S3/MinIO bucket when asked, refuses an empty dump, and requires an explicit
`ALLOW_DB_ONLY_RESTORE=1` rather than silently skipping documents.

---

## 4. Backup fails entirely when the document bucket is empty

**Symptom.** Every nightly backup fails. Log ends with:

```
tar: documents: Cannot stat: No such file or directory
[backup] ERROR: building the document archive failed
```

**Mechanism.** `mc mirror` does not create the target directory when the source
bucket is empty, so `tar` failed — taking the whole backup down with it,
including the database dump that had already succeeded. A pilot with no uploads
yet would fail every single night.

**Fix** (applied). The directory is created up front; an empty bucket yields a
valid empty archive plus a warning.

---

## 5. `x509: certificate signed by unknown authority` on the off-box upload

**Symptom.** The local backup works; the off-box sync fails with a TLS error
against `*.s3.*.amazonaws.com`.

**Mechanism.** The `postgres:16` base image ships **no CA certificates**
(`ca-certificates` is not installed, `/etc/ssl/certs/ca-certificates.crt` does
not exist), so `mc` cannot verify any HTTPS endpoint. Invisible when testing
against a plain-HTTP MinIO; it only appears against a real TLS target.

**Fix** (applied). `infrastructure/backup/Dockerfile` installs `ca-certificates`.

---

## 6. A deploy reports success without deploying

**Symptom.** The workflow is green. Production still runs the old images — check
with:

```bash
docker compose --env-file .env.prod -f compose.prod.yaml ps --format '{{.Service}} {{.Image}}'
```

The deploy log stops after an early step, yet the job exits 0.

**Mechanism.** The script was piped in with `ssh … 'bash -s' < script`, so bash
read it **from stdin**. `docker compose run` in step 2 attaches stdin and
consumed the rest of the script. Bash hit EOF and exited 0. Every later step —
pull, up, and all verification — never ran, and nothing could tell.

**Fix** (applied), at three levels:

- the workflow `scp`s the script and runs it as a file, with `ssh -n`
- `docker compose run` gets `-T` and `< /dev/null`
- a new step compares every container's image tag against `IMAGE_TAG` and fails
  the deploy if any is stale

The third matters most: the old verification asked whether the site was *healthy*,
never whether it was running the *new code* — and the old containers were
perfectly healthy.

---

## 7. A deploy silently upgrades PostgreSQL, Redis or ClamAV

**Symptom.** After an ordinary application deploy, `db`, `redis` or `clamav`
appear recreated. Readiness may fail briefly afterwards.

**Mechanism.** `docker compose pull` with no arguments pulls **every** service,
including floating tags such as `postgres:16` and `clamav:stable`. A new digest
makes compose recreate that container. ClamAV then needs minutes to reload its
signature databases, so `readiness/scanner` legitimately fails right after.

**Fix** (applied). `deploy-remote.sh` pulls only the seven services we build.
Third-party upgrades are now a deliberate, separate act.

---

## 8. Editing a config file appears to do nothing

**Symptom.** You edit `infrastructure/nginx/default.conf` or an entrypoint
script, run `up -d`, compose says `Running`, and the old behaviour persists.

**Mechanism.** Compose only recreates a container when its **image or spec**
changes. Bind-mounted file contents are neither.

**Fix.** Use `restart` (or `up -d --force-recreate <service>`):

```bash
docker compose --env-file .env.prod -f compose.prod.yaml restart api
```

Affects the nginx config and every entrypoint under `infrastructure/docker/`.

---

## 9. Unpinned base images drift and break the build

**Symptom.** A build that worked yesterday fails today with no code change.

**Mechanism.** `FROM php:8-fpm` floated to PHP 8.5, where `pecl install redis`
compiles but installs no module — breaking the backend image entirely.

**Fix** (applied). All base images are pinned: `php:8.3-fpm`,
`node:20-bookworm-slim`, `postgres:16`, `redis:7`,
`minio/minio:RELEASE.2025-09-07T16-13-09Z`,
`minio/mc:RELEASE.2025-08-13T08-35-41Z`.

When changing the `mc` version, re-check the read-back verification in
`backup-cron.sh`, which parses `mc stat --json`. A parser change there would
silently disable off-box verification.

---

## 10. Migrations: `schema:validate` disagrees between SQLite and PostgreSQL

**Symptom.** Tests pass on SQLite; `doctrine:schema:validate` reports "not in
sync" on PostgreSQL.

**Mechanism.** Hand-written migrations must use Doctrine's foreign-key index
naming: `IDX_<crc32(table)><crc32(column)>`. SQLite builds the schema from the
mapping and so never notices a mismatch.

Related SQLite/PostgreSQL divergences:

- `schema:create` on SQLite degrades **partial unique indexes** into full ones.
  Production PostgreSQL, built via migrations, keeps the partial index.
- The SQLite test database **persists between runs** — tests must use unique keys
  per run.

---

## 11. The messenger table race

**Symptom.** Non-deterministic start-up failure: the backend's migration fails on
a duplicate `messenger_messages` table. Never reproducible in CI.

**Mechanism.** With `auto_setup=1` the worker and the backend started
simultaneously; the worker created the table first and the migration then failed.

**Fix** (applied). Two layers: `MESSENGER_TRANSPORT_DSN` uses `auto_setup=0`
(migrations own the schema), and ordering is enforced by the one-shot `migrate`
service that both `backend` and `worker` wait on via
`service_completed_successfully`.

---

## Environment-specific gotchas

**ClamAV has no arm64 image.** `no matching manifest for linux/arm64/v8`. It
cannot run on Apple Silicon, so the scanner path cannot be exercised locally
there. Production is x86_64.

**`sudo` plus a glob does not do what you expect.**
`sudo ls /var/lib/docker/volumes/…/*/db.sql.gz` expands the glob as *your* user,
who cannot read the directory, so the literal `*` is passed through and you get
"No such file or directory". Use `sudo sh -c 'ls …'` or list the directory first.

**Long commands break when pasted over SSH.** Heredocs hang at the `>` prompt and
crontab entries split across lines and are rejected with `bad minute`. Prefer
short single-line commands; use an editor for file contents.

---

## First response to anything

```bash
cd /opt/bcss

# The same checks the monitoring runs
BASE_URL=https://app.bcss.ro bash infrastructure/monitoring/healthcheck.sh

# What is actually running, and on which images?
docker compose --env-file .env.prod -f compose.prod.yaml ps --format '{{.Service}} {{.State}} {{.Image}}'

# Recent failures
docker compose --env-file .env.prod -f compose.prod.yaml logs --since 1h | grep -iE 'error|fatal|fail'
```

If the frontends load but the API does not, start at §1 — that is the most likely
cause and the quickest to rule out.
