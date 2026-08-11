# Restore — drill register and history

> A backup without a tested restore is not a backup.

**Procedures now live in [`docs/BACKUP_AND_RESTORE.md`](../../docs/BACKUP_AND_RESTORE.md)** —
how to back up, how to restore over production, how to recover when the instance
is gone. This file is the *record*: which drills were run, what they found, and
what the measured numbers actually mean.

The drill is automated (`scripts/restore-drill.sh`, monthly on the 1st at 06:00
UTC), so this register grows on its own. Add a row whenever a drill is run by
hand or an automated one is worth commenting on.

---

## Scripts in this directory

| Script | Purpose |
|---|---|
| `backup-cron.sh` | The production scheduler: nightly dump + document mirror + off-box copy + retention |
| `backup.sh` | The `STORAGE_DRIVER=local` variant, for a host cron |
| `restore.sh` | Restores a backup into an explicitly named target; clears the target schema itself |
| `fetch-offsite.sh` | Brings a backup back from the off-box bucket (`--list`, `--latest`, or a timestamp) |

All four ship inside the backup image, so a restore runs in the same container
that produced the backup — the same `pg_dump`/`psql` and `mc` versions, with no
separate tooling.

---

## Mandatory alerts

- Backup failed (non-zero exit from the `backup` service).
- **Off-box sync failed** — the exit code is non-zero even when the local copy
  succeeded.
- No backup in the last 26 hours (`healthcheck.sh` with `BACKUP_DIR`).
- Storage unavailable or disk above threshold (`healthcheck.sh`).

All of these are wired: see [`docs/MONITORING.md`](../../docs/MONITORING.md).

---

## Drill register

| Date | Environment | Volume | Measured RTO | RPO | Result |
|---|---|---|---|---|---|
| 2026-08-04 | Local isolated (Docker, Apple Silicon) | 8 KB DB (20 migrations, 2 users, 2 vehicles, 4 deadlines) + 5 documents / 1 MB | backup < 1s, restore ~1s | 24h | **Passed** — identical row counts, 20 migrations preserved, documents byte-identical (`diff -r`), `schema:validate` in sync |
| 2026-08-04 | Local isolated — **disaster** drill (local backups deleted, recovery from the bucket only) | 8 KB DB + 3 documents / 441 KB | fetch + restore ~2s | 24h | **Passed** — `--list` → `--latest` → `restore.sh`; 2 vehicles, 20 migrations, byte-identical documents. The off-box store was local MinIO as an S3-compatible stand-in, **not** Lightsail |
| 2026-08-11 | **PRODUCTION** (Lightsail eu-central-1) — backup → bucket `backup-bcss` → fetch → restore into an isolated database on the same instance | 6923 B DB (1 user, 0 vehicles), 0 documents | full cycle under 1 min | 24h | **Passed** — `off-box verification OK (6923 B)`; restored FROM the copy fetched out of Lightsail; `users`/`vehicles`/`deadlines`/`migrations` identical to production. Object versioning enabled on the bucket |
| 2026-08-11 | Local isolated — **restore over a populated database** | 8 KB DB, 4 deadlines deliberately deleted first | ~2s | n/a | **Passed** — the four deleted deadlines returned, `schema:validate` in sync, writers restarted, and the pre-restore safety backup was preserved. All three guards verified to refuse without touching production |

---

## What the first drill found

The procedure had never been executed. Running it uncovered three real defects,
all fixed in the same commit:

1. **`pg_dump` did not accept the DSN from `.env.prod.example`.** `serverVersion`
   and `charset` are Doctrine parameters, not libpq ones →
   `invalid URI query parameter`.
2. **The failure was invisible.** `pg_dump … | gzip > f` returns *gzip's* exit
   status, so a failed dump produced an empty 20-byte archive that `gzip -t`
   validated. The backup reported success with no database in it.
3. **The restore lost every document.** `backup-cron.sh` writes
   `documents.tar.gz`; `restore.sh` looked for `storage.tar.gz`, did not find it,
   printed a warning and exited **0** — restoring only the database.

Together, the first two mean production backups taken before this fix **must be
treated as having no database**. Check the size of any existing archive:
`ls -lh /backups/*/db.sql.gz` — anything around 20 bytes is empty.

## What the production drill found

Run on 2026-08-11, it confirmed that all seven existing backups (5-11 August) had
a 20-byte `db.sql.gz` — production's database had never actually been backed up.
Documents were the same story: the bucket was empty, so `tar` failed and took the
whole run with it.

It also surfaced two defects that appear **only** in production, not locally:

1. **The backup image had no CA certificates.** `postgres:16` does not install
   `ca-certificates`, so `mc` rejected every HTTPS endpoint with
   `x509: certificate signed by unknown authority`. Invisible locally, where the
   off-box target was MinIO over plain `http://`.
2. **nginx did not re-resolve its upstream.** Recreating the `backend` container
   gives it a new IP, but `fastcgi_pass backend:9000` (a literal name) kept the
   old one → 502 across all of `/api` with a perfectly healthy backend. See the
   comment in `infrastructure/nginx/default.conf`.

## What the drill caught in its own tooling

On 2026-08-11 the automated drill failed with:

```
[FAIL] vehicle_deadlines: production=4 but restored=0 — empty backup?
```

Not a false alarm. The pre-restore safety backup taken by
`restore-production.sh` had captured the **broken** state and pushed it into the
normal off-box rotation, making a known-bad snapshot the newest backup — exactly
what a later `--latest` would pick up. The safety copy now goes to a separate
`<prefix>-pre-restore` path.

Worth recording because it is the system working as intended: an automated check
found a defect in a script that had just been written and reviewed.

---

## About the numbers

**The measured RTO is a lower bound, not a promise.** It was obtained on a tiny
dataset, on a laptop or a near-empty production database, with the database and
bucket on the same network. What it demonstrates is that the *procedure* works
end to end and that the checks catch regressions. Real RTO scales with document
volume (`mc mirror` dominates) and must be re-measured on the server with real
data before promising anything to a customer.

**RPO is 24 hours**, structurally: the `backup` service runs once a day at
`BACKUP_HOUR` (default 03:00 UTC). That is not a measurement, it is a consequence
of the schedule. For vehicle records belonging to private individuals, losing up
to a day of work is a product decision worth confirming explicitly; if it is not
acceptable, the options are more frequent backups or continuous WAL archiving.

**The current drill's data assertions are weak**, because production holds one
user and no vehicles or documents. The table-set and migration-count checks are
meaningful; the row-count comparison is not, yet. It strengthens automatically as
real data accumulates.
