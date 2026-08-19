# Backup and restore

The guiding rule: **a backup without a tested restore is not a backup.** This
system was shipping empty backups for seven consecutive nights and reporting
success every time. Everything below is shaped by that.

Scripts live in `infrastructure/backup/` and `scripts/`. The drill log and
history are in [`../infrastructure/backup/restore.md`](../infrastructure/backup/restore.md).

---

## 1. What gets backed up, and where

Every night at **03:00 UTC**, the `backup` service:

1. `pg_dump` of the database → `db.sql.gz`
2. `mc mirror` of the MinIO document bucket → `documents.tar.gz`
3. Both written to the `backups` volume, under `/backups/<YYYYmmdd-HHMMSS>/`
4. Both uploaded to Lightsail object storage, bucket `backup-bcss`, prefix `bcss/`
5. Retention applied: 14 days locally, 30 days off-box

| | Local | Off-box (Lightsail) |
|---|---|---|
| Location | `backups` Docker volume | `backup-bcss/bcss/` |
| Retention | 14 days | 30 days |
| Object versioning | n/a | enabled |
| Survives instance loss | **No** | Yes |

The off-box copy is the one that matters. A backup on the disk it protects is
not a backup: if the instance is lost, the volume goes with it.

## 2. Does any of this touch live data?

**No. Nothing automated ever writes to production.**

| Component | Effect on production |
|---|---|
| `backup-cron.sh` (nightly) | `pg_dump` reads. `mc mirror` reads the bucket. Writes only to the backup volume and the off-box bucket |
| `fetch-offsite.sh` | Downloads into `/backups/restored/`. Never writes to the database or document bucket |
| `restore-drill.sh` (monthly) | Creates a **separate** database `drill_<timestamp>`, restores into it, drops it |
| `restore.sh` | **Writes** — but only where explicitly told |
| `restore-production.sh` | **Destructive by design** — see §4 |

`restore.sh` refuses to run without an explicit target:

```
[restore] ERROR: DATABASE_URL_RESTORE is not set (refusing to guess the target).
```

Three precise caveats:

- The monthly drill *does* connect to the production PostgreSQL **server** — it
  has to, to create its scratch database. It never touches the `bcsc` database,
  and a guard refuses to proceed if the drill name ever equalled production's.
- The drill restores documents into the throwaway container's own filesystem,
  which disappears with `--rm`. It does not pass `S3_BUCKET_RESTORE`, so the live
  document bucket is never written to.
- Running `restore.sh` with `S3_BUCKET_RESTORE=bcsc-documents` **would** overwrite
  live documents. That requires deliberate intent, and it is the correct
  behaviour for a real recovery.

## 3. Verifying a backup

```bash
cd /opt/bcss

# Trigger one now
docker compose --env-file .env.prod -f compose.prod.yaml run --rm -e BACKUP_ONESHOT=1 backup

# What exists off-box?
docker compose --env-file .env.prod -f compose.prod.yaml run --rm \
  --entrypoint fetch-offsite.sh backup --list
```

A healthy run prints:

```
[backup] database archive: 6923 B          ← thousands, not 20
[backup] documents saved: 0
[backup] off-box sync -> https://s3.eu-central-1.amazonaws.com/backup-bcss/bcss
[backup] off-box verification OK (6923 B)  ← re-read from the bucket and size-matched
```

**`off-box verification OK` is the line that matters.** It means the upload was
read back and its size compared, not merely that the command exited 0.

`documents saved: 0` is currently correct — no customer documents exist yet. The
empty case produces a valid empty archive plus a warning; it used to abort the
entire backup, database included.

## 4. Restoring over production

Destructive. One command:

```bash
cd /opt/bcss
CONFIRM=RESTORE-PRODUCTION ./scripts/restore-production.sh --latest
```

`--latest` fetches the newest copy from Lightsail. For a specific backup, pass
the path instead:

```bash
CONFIRM=RESTORE-PRODUCTION ./scripts/restore-production.sh /backups/restored/20260811-030000
```

There are no manual steps. The script:

| Step | Action |
|---|---|
| 0 | Validates the source archive **before** touching anything |
| 1 | Backs up the **current** state (to a separate `-pre-restore` off-box prefix) |
| 2 | Stops `backend`, `worker`, `scheduler`; `db` and MinIO stay up |
| 3 | Closes leftover connections |
| 4 | Restores (`restore.sh` clears the target schema itself) |
| 5 | Restarts and verifies migrations + readiness |

Guards, all tested: no `CONFIRM` → refuses; an invalid or empty archive → stops
before touching production; a failed restore → leaves the writers **stopped**
deliberately, because unavailable beats serving from a half-restored schema.

**Documents are not restored by default** — database only. Add
`RESTORE_DOCUMENTS=1` to include them, bearing in mind that `mc mirror`
overwrites and adds but does **not** delete objects present live and absent from
the backup. It is not an exact mirror.

Step 1's safety copy goes to a *separate* prefix on purpose. It is a snapshot of
a state known to be broken; landing it in the normal rotation would make it "the
most recent backup" and the next `--latest` would pick it up.

### Why not just `restore.sh`?

`restore.sh` runs *inside* the backup container, which has no Docker access and
therefore cannot stop `backend`/`worker`/`scheduler`. A schema must not be
swapped underneath a running application. Stopping the writers has to happen on
the host, and that is the only thing `restore-production.sh` adds.

`restore.sh` clears its own target schema and refuses a database with active
connections, pointing at the production script instead.

## 5. Disaster: the instance is gone

The scenario the off-box copy exists for. On a fresh machine, a repository
checkout plus `.env.prod` with the `OFFSITE_*` values is enough:

```bash
# 1) what exists remotely?
docker compose --env-file .env.prod -f compose.prod.yaml run --rm \
  --entrypoint fetch-offsite.sh backup --list

# 2) bring the newest one down (verified on download)
docker compose --env-file .env.prod -f compose.prod.yaml run --rm \
  --entrypoint fetch-offsite.sh backup --latest

# 3) restore it
CONFIRM=RESTORE-PRODUCTION ./scripts/restore-production.sh /backups/restored/<timestamp>
```

`fetch-offsite.sh` verifies each archive immediately after download — gzip
integrity plus the dump's closing marker — so corruption in transit is caught
before a restore rather than during one.

## 6. The automated monthly drill

`scripts/restore-drill.sh` runs at 06:00 UTC on the 1st. It fetches the newest
backup **from the bucket**, restores it into a single-use database, and asserts:

- the table set matches production (a truncated dump restores a subset happily)
- the migration history count matches (without it, the next deploy re-applies)
- no table is empty in the restore while production has rows — the signature of
  the empty-dump regression

Production is never touched, and the scratch database is dropped by an `EXIT`
trap even if the drill fails.

This exists because "the restore was tested" is true on exactly the day someone
tested it. Three months later an unverified backup is an assumption again.

Run it by hand any time:

```bash
sudo /opt/bcss/scripts/restore-drill.sh
```

## 7. Restoring into an isolated target

For inspection, or a parallel environment — not production:

```bash
docker compose --env-file .env.prod -f compose.prod.yaml exec db \
  psql -U bcsc -d postgres -c 'CREATE DATABASE scratch OWNER bcsc;'

docker compose --env-file .env.prod -f compose.prod.yaml run --rm \
  -e DATABASE_URL_RESTORE="postgresql://bcsc:<password>@db:5432/scratch" \
  --entrypoint restore.sh backup /backups/<timestamp>
```

Add `S3_ENDPOINT_RESTORE` / `S3_BUCKET_RESTORE` / `S3_KEY_RESTORE` /
`S3_SECRET_RESTORE` to restore documents into a bucket; without them documents
go to local disk inside the container.

## 8. Post-restore checks

"It ran without error" is not "the data is there":

1. **Row counts**, source vs restored, on the tables that matter
2. **Migration history** — `SELECT count(*) FROM doctrine_migration_versions;`
3. **Schema matches the mapping** — `doctrine:schema:validate` reports *in sync*
4. **Document integrity at byte level** — `diff -r`, not just an object count
5. **Readiness** — `GET /api/health/ready` returns 200
6. A test customer sees their vehicles and downloads a document

## 9. Known limits

- **RPO is 24 hours**, structurally: one backup a day. A restore loses everything
  since 03:00 UTC. Fine now; a product decision to confirm before real customer
  records accumulate.
- **The drill's data comparison is currently weak.** Production holds one user
  and no vehicles or documents, so the row-count assertions pass trivially. It
  strengthens automatically as data arrives.
- **Backups protect data, not the machine.** Rebuilding the instance itself is
  manual; Lightsail daily snapshots cover the machine.
