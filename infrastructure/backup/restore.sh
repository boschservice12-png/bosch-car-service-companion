#!/usr/bin/env bash
# Restore from a backup — the scriptable companion to the drill in restore.md.
# A backup without a tested restore is NOT a backup: run this monthly against an
# isolated target (empty database, empty storage) and record RTO/RPO.
#
# Usage:
#   DATABASE_URL_RESTORE=postgresql://… STORAGE_DIR=/app/var/storage \
#     ./restore.sh /backups/20260720-031500
#
#   # restore into an S3/MinIO bucket (PRODUCTION layout):
#   DATABASE_URL_RESTORE=postgresql://… \
#     S3_ENDPOINT_RESTORE=http://minio:9000 S3_BUCKET_RESTORE=bcsc-documents \
#     S3_KEY_RESTORE=… S3_SECRET_RESTORE=… ./restore.sh /backups/20260720-031500
#
# To restore OVER production, do not call this directly — use
# scripts/restore-production.sh, which also stops the writers first.
#
# Variables:
#   DATABASE_URL_RESTORE  (required) — target DSN for psql (NOT production!)
#   STORAGE_DIR           (default /app/var/storage) — where documents are
#                         extracted when restoring to local disk (STORAGE_DRIVER=local)
#   S3_*_RESTORE          — if S3_ENDPOINT_RESTORE is set, documents are restored
#                           into the bucket with `mc mirror` (STORAGE_DRIVER=s3)
#   ALLOW_DB_ONLY_RESTORE=1 — explicitly accept a backup with no documents
#   RESTORE_KEEP_SCHEMA=1   — skip clearing the target schema
set -euo pipefail

SRC="${1:-}"
if [ -z "${SRC}" ] || [ ! -d "${SRC}" ]; then
  echo "Usage: DATABASE_URL_RESTORE=… ./restore.sh <backup-directory>" >&2
  exit 2
fi
if [ -z "${DATABASE_URL_RESTORE:-}" ]; then
  echo "[restore] ERROR: DATABASE_URL_RESTORE is not set (refusing to guess the target)." >&2
  exit 2
fi

STORAGE="${STORAGE_DIR:-/app/var/storage}"
DB_ARCHIVE="${SRC}/db.sql.gz"

# The TWO backup layouts in this repo — both must be accepted:
#   documents.tar.gz  — written by backup-cron.sh (PRODUCTION, `mc mirror` from the bucket)
#   storage.tar.gz    — written by backup.sh      (`local` driver, tar from disk)
# This script used to look for storage.tar.gz ONLY, so against a production
# backup it reported "only the database was restored" and exited 0 — losing
# EVERY document without failing. A restore that half succeeds is more dangerous
# than one that crashes.
if [ -f "${SRC}/documents.tar.gz" ]; then
  DOC_ARCHIVE="${SRC}/documents.tar.gz"
  DOC_LAYOUT="documents"   # the archive contains a `documents/` directory
elif [ -f "${SRC}/storage.tar.gz" ]; then
  DOC_ARCHIVE="${SRC}/storage.tar.gz"
  DOC_LAYOUT="flat"        # the archive contains the storage contents directly
else
  DOC_ARCHIVE=""
  DOC_LAYOUT=""
fi

# The Doctrine DSN carries `serverVersion` / `charset`, which libpq does not
# know — psql would exit with "invalid URI query parameter". Strip those, keep
# everything else.
pg_dsn() {
  printf '%s' "$1" | sed -E 's/([?&])(serverVersion|charset)=[^&]*/\1/g; s/&&+/\&/g; s/[?&]+$//; s/\?&/?/'
}

# 1) Archive integrity before touching the target — a corrupt archive stops the
#    restore early rather than halfway through.
echo "[restore] checking archive integrity…"
[ -f "${DB_ARCHIVE}" ] || { echo "[restore] missing ${DB_ARCHIVE}" >&2; exit 1; }
gzip -t "${DB_ARCHIVE}"

# `gzip -t` also passes on an EMPTY archive (20 bytes) — exactly what a failed
# pg_dump produced before the fix in backup-cron.sh. So check the CONTENT, not
# just integrity: otherwise we "successfully" restore a completely empty database.
if ! gunzip -c "${DB_ARCHIVE}" | grep -q "PostgreSQL database dump complete"; then
  echo "[restore] ERROR: ${DB_ARCHIVE} is not a complete dump (empty or truncated)." >&2
  echo "[restore] The target was NOT touched. Check the log of the backup that produced it." >&2
  exit 1
fi

if [ -n "${DOC_ARCHIVE}" ]; then
  gzip -t "${DOC_ARCHIVE}"
  echo "[restore] document archive: $(basename "${DOC_ARCHIVE}") (layout: ${DOC_LAYOUT})"
elif [ "${ALLOW_DB_ONLY_RESTORE:-0}" = "1" ]; then
  echo "[restore] WARNING: backup without documents, explicitly accepted (ALLOW_DB_ONLY_RESTORE=1)." >&2
else
  echo "[restore] ERROR: ${SRC} contains neither documents.tar.gz nor storage.tar.gz." >&2
  echo "[restore] The database was NOT touched. A database-only restore leaves document" >&2
  echo "[restore] records without their files. If that is really what you want: ALLOW_DB_ONLY_RESTORE=1." >&2
  exit 1
fi

# 2) The database.
TARGET_DSN="$(pg_dsn "${DATABASE_URL_RESTORE}")"

# Clear the target schema BEFORE restoring. The dump contains `CREATE TABLE`
# without `DROP`, so against a database that already has the schema psql stopped
# at:
#   ERROR: relation "application_settings" already exists
# With ON_ERROR_STOP that is a CLEAN failure (nothing half-applied), but it also
# meant restoring over an existing database simply did not work, and the
# operator had to know to run a manual DROP SCHEMA first — exactly the kind of
# step that gets forgotten at 3am. The script now does it itself.
#
# The target is ALWAYS explicit (DATABASE_URL_RESTORE is mandatory and the
# script refuses to guess), so we only clear what we were explicitly told to
# overwrite. RESTORE_KEEP_SCHEMA=1 skips this, for a target known to be empty.
if [ "${RESTORE_KEEP_SCHEMA:-0}" != "1" ]; then
  # If anyone else is connected to the target it is almost certainly a LIVE
  # database with active writers. Do not pull the schema out from under a
  # running application.
  OTHERS="$(psql -tAX "${TARGET_DSN}" -c \
    "SELECT count(*) FROM pg_stat_activity WHERE datname = current_database() AND pid <> pg_backend_pid();" 2>/dev/null || echo 0)"
  if [ "${OTHERS:-0}" -gt 0 ]; then
    echo "[restore] ERROR: the target has ${OTHERS} active connection(s) — it looks like a LIVE database." >&2
    echo "[restore] Refusing to clear the schema under a running application. For production use:" >&2
    echo "[restore]   CONFIRM=RESTORE-PRODUCTION ./scripts/restore-production.sh <backup>" >&2
    echo "[restore] (it stops backend/worker/scheduler first). The target was NOT touched." >&2
    exit 1
  fi
  echo "[restore] clearing the target schema…"
  psql --quiet --set ON_ERROR_STOP=on "${TARGET_DSN}" \
    -c "DROP SCHEMA IF EXISTS public CASCADE; CREATE SCHEMA public;"
fi

echo "[restore] restoring the database -> DATABASE_URL_RESTORE"
gunzip -c "${DB_ARCHIVE}" | psql --quiet --set ON_ERROR_STOP=on "${TARGET_DSN}"

# 3) Documents — to local disk or back into the bucket, depending on config.
if [ -n "${DOC_ARCHIVE}" ]; then
  if [ -n "${S3_ENDPOINT_RESTORE:-}" ]; then
    : "${S3_BUCKET_RESTORE:?[restore] S3_BUCKET_RESTORE is missing}"
    : "${S3_KEY_RESTORE:?[restore] S3_KEY_RESTORE is missing}"
    : "${S3_SECRET_RESTORE:?[restore] S3_SECRET_RESTORE is missing}"
    command -v mc >/dev/null 2>&1 || { echo "[restore] ERROR: mc is missing (use the backup image)." >&2; exit 1; }

    TMP="$(mktemp -d)"
    trap 'rm -rf "${TMP}"' EXIT
    tar -xzf "${DOC_ARCHIVE}" -C "${TMP}"
    # Normalise both layouts to a single source directory.
    if [ "${DOC_LAYOUT}" = "documents" ]; then
      SRC_DIR="${TMP}/documents"
    else
      SRC_DIR="${TMP}"
    fi

    echo "[restore] restoring documents -> ${S3_ENDPOINT_RESTORE}/${S3_BUCKET_RESTORE}"
    mc alias set rst "${S3_ENDPOINT_RESTORE}" "${S3_KEY_RESTORE}" "${S3_SECRET_RESTORE}" >/dev/null
    mc mb -p "rst/${S3_BUCKET_RESTORE}" >/dev/null 2>&1 || true
    mc anonymous set none "rst/${S3_BUCKET_RESTORE}" >/dev/null 2>&1 || true
    mc mirror --overwrite "${SRC_DIR}" "rst/${S3_BUCKET_RESTORE}"

    RESTORED="$(mc ls --recursive "rst/${S3_BUCKET_RESTORE}" | wc -l | tr -d ' ')"
    echo "[restore] objects in the bucket after restore: ${RESTORED}"
  else
    echo "[restore] restoring documents -> ${STORAGE}"
    mkdir -p "${STORAGE}"
    if [ "${DOC_LAYOUT}" = "documents" ]; then
      # The production archive has a `documents/` prefix; flatten it so local
      # storage looks identical to what backup.sh produces.
      tar -xzf "${DOC_ARCHIVE}" -C "${STORAGE}" --strip-components=1
    else
      tar -xzf "${DOC_ARCHIVE}" -C "${STORAGE}"
    fi
    echo "[restore] files restored: $(find "${STORAGE}" -type f | wc -l | tr -d ' ')"
  fi
fi

echo "[restore] done. Post-restore checks (see restore.md):"
echo "  - doctrine:migrations:status  (migrations up to date)"
echo "  - GET /api/health/ready  =>  200 (deep readiness)"
echo "  - a test customer sees their vehicles and downloads a document"
echo "  - record the date, RTO (duration) and RPO (maximum data loss)"
