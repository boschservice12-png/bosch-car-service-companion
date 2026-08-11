#!/usr/bin/env bash
# Daily backup — database + uploaded documents (local storage driver).
#
# This is the `STORAGE_DRIVER=local` variant, intended to be run from cron on a
# host. PRODUCTION uses backup-cron.sh instead, which also mirrors the S3/MinIO
# bucket and pushes an off-box copy. Restores are TESTED periodically following
# the procedure in restore.md.
#
# Variables:
#   DATABASE_URL      (required) — PostgreSQL DSN for pg_dump
#   STORAGE_DIR       (default /app/var/storage) — the application's documents
#   BACKUP_DIR        (default /backups) — archive destination
#   BACKUP_KEEP_DAYS  (default 14) — retention; older archives are deleted
set -euo pipefail

TS="$(date +%Y%m%d-%H%M%S)"
ROOT="${BACKUP_DIR:-/backups}"
DEST="${ROOT}/${TS}"
STORAGE="${STORAGE_DIR:-/app/var/storage}"
KEEP_DAYS="${BACKUP_KEEP_DAYS:-14}"
mkdir -p "${DEST}"

# The Doctrine DSN carries `serverVersion` / `charset`, which libpq does NOT
# know — pg_dump would exit with "invalid URI query parameter". Strip those,
# keep every other parameter (e.g. sslmode).
pg_dsn() {
  printf '%s' "$1" | sed -E 's/([?&])(serverVersion|charset)=[^&]*/\1/g; s/&&+/\&/g; s/[?&]+$//; s/\?&/?/'
}

echo "[backup] pg_dump -> ${DEST}/db.sql.gz"
# A separate step, not `pg_dump | gzip`: in a pipeline the exit code is gzip's,
# so a failed pg_dump would produce an EMPTY but valid archive that `gzip -t`
# accepts. (`set -o pipefail` is active here, but we write it out explicitly so
# this matches backup-cron.sh, which runs under `sh` and has no pipefail.)
pg_dump "$(pg_dsn "${DATABASE_URL}")" > "${DEST}/db.sql"
grep -q "PostgreSQL database dump complete" "${DEST}/db.sql" \
  || { echo "[backup] ERROR: truncated dump (closing marker missing)" >&2; exit 1; }
grep -qE "^(CREATE TABLE|COPY )" "${DEST}/db.sql" \
  || { echo "[backup] ERROR: dump contains no schema or data" >&2; exit 1; }
gzip -f "${DEST}/db.sql"
# Integrity check: a corrupt archive is worse than a missing one.
gzip -t "${DEST}/db.sql.gz"

if [ -d "${STORAGE}" ]; then
  echo "[backup] documents -> ${DEST}/storage.tar.gz"
  tar -czf "${DEST}/storage.tar.gz" -C "${STORAGE}" .
  gzip -t "${DEST}/storage.tar.gz"
else
  echo "[backup] WARNING: ${STORAGE} does not exist — saving the database only." >&2
fi

echo "[backup] retention: keeping ${KEEP_DAYS} days"
find "${ROOT}" -mindepth 1 -maxdepth 1 -type d -mtime "+${KEEP_DAYS}" -exec rm -rf {} +

echo "[backup] done: ${DEST}"
ls -lh "${DEST}"
# On failure the script exits non-zero (set -e). Cron/monitoring must alert on a
# non-zero exit code AND on "last backup older than 24h" (see healthcheck.sh,
# the freshness check).
