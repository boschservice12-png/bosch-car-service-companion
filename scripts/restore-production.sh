#!/usr/bin/env bash
# RESTORE OVER PRODUCTION. The only script in this repo that destroys real data.
#
#   CONFIRM=RESTORE-PRODUCTION ./scripts/restore-production.sh --latest
#   CONFIRM=RESTORE-PRODUCTION ./scripts/restore-production.sh /backups/restored/20260811-030000
#
# Why a script rather than a list of steps in a document: the procedure has five
# steps that must happen in order, and whoever runs it does so at the worst
# possible moment — production is down, it is the middle of the night, and a
# skipped command means either a half-finished restore or the loss of the
# current state.
#
# What it does, in order:
#   1. BACKS UP the CURRENT state (before destroying it)
#   2. stops the writers (backend, worker, scheduler); db and MinIO stay up
#   3. closes leftover connections
#   4. restores from the given backup (restore.sh clears the schema itself)
#   5. restarts and verifies
#
# Documents are NOT touched by default. See RESTORE_DOCUMENTS below.
set -euo pipefail

SRC="${1:-}"
APP_DIR="${APP_DIR:-/opt/bcss}"
cd "${APP_DIR}"
COMPOSE=(docker compose --env-file .env.prod -f compose.prod.yaml)
WRITERS=(backend worker scheduler)

say() { echo; echo "── $* ──"; }
die() { echo "ERROR: $*" >&2; exit 1; }

# --- Guards -------------------------------------------------------------------
[ -n "${SRC}" ] || die "Usage: CONFIRM=RESTORE-PRODUCTION $0 <backup-directory>|--latest"
[ "${CONFIRM:-}" = "RESTORE-PRODUCTION" ] || cat >&2 <<EOF
ERROR: confirmation missing.

This script DELETES the production database and replaces it with the contents of
   ${SRC}

If that is really what you want:
   CONFIRM=RESTORE-PRODUCTION $0 ${SRC}
EOF
[ "${CONFIRM:-}" = "RESTORE-PRODUCTION" ] || exit 1

[ -f .env.prod ] || die "${APP_DIR}/.env.prod is missing."
set -a; . ./.env.prod; set +a
: "${POSTGRES_PASSWORD:?missing from .env.prod}"
PROD_DB="${POSTGRES_DB:-bcsc}"
PGUSER_="${POSTGRES_USER:-bcsc}"

psql_() { "${COMPOSE[@]}" exec -T db psql -U "${PGUSER_}" "$@"; }

# `--latest` = fetch the most recent copy from the off-box store and restore it.
# This is the real emergency case: the instance is broken and whoever is fixing
# it should not have to hunt for timestamps in a bucket.
if [ "${SRC}" = "--latest" ]; then
  say "fetching the most recent backup from the off-box store"
  FETCH_OUT="$("${COMPOSE[@]}" run --rm --no-deps -T --entrypoint fetch-offsite.sh backup --latest < /dev/null 2>&1)" \
    || { echo "${FETCH_OUT}" >&2; die "could not fetch the off-box backup"; }
  SRC="$(printf '%s' "${FETCH_OUT}" | grep -oE '/backups/restored/[0-9]{8}-[0-9]{6}' | tail -1)"
  [ -n "${SRC}" ] || die "could not determine the fetched directory"
  echo "fetched: ${SRC}"
fi

# The named backup must exist AND contain a real dump. Without this check we
# could empty production and only then discover the archive was empty.
"${COMPOSE[@]}" run --rm --no-deps -T --entrypoint sh backup -c "
  set -e
  [ -f '${SRC}/db.sql.gz' ] || { echo 'missing ${SRC}/db.sql.gz'; exit 1; }
  gzip -t '${SRC}/db.sql.gz'
  gunzip -c '${SRC}/db.sql.gz' | grep -q 'PostgreSQL database dump complete'
" < /dev/null >/dev/null 2>&1 \
  || die "${SRC} does not contain a complete, valid dump. Production was NOT touched."

say "0. source validated: ${SRC}"
echo "target: production database '${PROD_DB}'"

# --- 1. the safety net --------------------------------------------------------
say "1. backing up the CURRENT state (before destroying it)"
# If the wrong backup gets restored, this is the only way back.
#
# It goes to a SEPARATE off-box prefix, not the normal rotation. It is a copy of
# a state we know is broken — that is why we are restoring over it. Landing in
# the normal rotation would make it "the most recent backup", which is exactly
# what a later `--latest` or the monthly drill would pick up. (Caught by the
# drill itself on 2026-08-11, which correctly reported
# `vehicle_deadlines: production=4 but restored=0`.)
"${COMPOSE[@]}" run --rm -T \
  -e BACKUP_ONESHOT=1 \
  -e OFFSITE_PREFIX="${OFFSITE_PREFIX:-bcss}-pre-restore" \
  backup < /dev/null \
  || die "the safety backup failed — refusing to continue without it"
echo "(off-box copy of the pre-restore state: prefix '${OFFSITE_PREFIX:-bcss}-pre-restore')"

# --- 2. stop the writers ------------------------------------------------------
say "2. stopping the writers: ${WRITERS[*]}"
# db and MinIO stay up (we need them). The frontends stay up too and will return
# API errors — acceptable: production is unavailable during a restore anyway,
# and a page that loads beats a dead one.
"${COMPOSE[@]}" stop "${WRITERS[@]}"

# --- 3. close leftover connections --------------------------------------------
say "3. closing leftover connections"
# Clearing the schema is restore.sh's job. Here we only make sure nobody is
# still connected: restore.sh REFUSES to clear a database with active
# connections (its protection against wiping the schema under a running app),
# and a container `stop` can leave a dying connection behind.
psql_ -d postgres -c \
  "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname='${PROD_DB}' AND pid <> pg_backend_pid();" >/dev/null
echo "connections closed"

# --- 4. restore ---------------------------------------------------------------
say "4. restoring from ${SRC}"
"${COMPOSE[@]}" run --rm --no-deps -T \
  -e DATABASE_URL_RESTORE="postgresql://${PGUSER_}:${POSTGRES_PASSWORD}@db:5432/${PROD_DB}" \
  ${RESTORE_DOCUMENTS:+-e S3_ENDPOINT_RESTORE=${S3_ENDPOINT}} \
  ${RESTORE_DOCUMENTS:+-e S3_BUCKET_RESTORE=${S3_BUCKET}} \
  ${RESTORE_DOCUMENTS:+-e S3_KEY_RESTORE=${S3_KEY}} \
  ${RESTORE_DOCUMENTS:+-e S3_SECRET_RESTORE=${S3_SECRET}} \
  --entrypoint restore.sh backup "${SRC}" < /dev/null \
  || die "the restore failed. The writers are still STOPPED — the schema is empty or partial. Do NOT restart until a restore succeeds."

if [ -z "${RESTORE_DOCUMENTS:-}" ]; then
  echo
  echo "NOTE: documents in the bucket were NOT touched (database only)."
  echo "To restore documents as well: RESTORE_DOCUMENTS=1 $0 ${SRC}"
  echo "Caution: mc mirror overwrites and adds, but does NOT delete objects that"
  echo "exist live and are missing from the backup. It is not an exact mirror."
fi

# --- 5. restart and verify ----------------------------------------------------
say "5. restarting and verifying"
"${COMPOSE[@]}" up -d "${WRITERS[@]}"

MIG="$(psql_ -t -A -d "${PROD_DB}" -c 'SELECT count(*) FROM doctrine_migration_versions;' | tr -d '\r')"
echo "migrations restored: ${MIG}"

BASE_URL="${DEPLOY_BASE_URL:-https://app.bcss.ro}"
for i in $(seq 1 24); do
  curl -fsS --max-time 5 "${BASE_URL}/api/health" >/dev/null 2>&1 && { echo "liveness OK after ~$((i*5))s"; break; }
  [ "${i}" -eq 24 ] && die "/api/health did not respond within 120s — check 'logs backend'"
  sleep 5
done
BASE_URL="${BASE_URL}" NONCRITICAL_MODE=warn bash infrastructure/monitoring/healthcheck.sh

say "restore complete, from ${SRC}"
echo "The backup of the pre-restore state remains in /backups (step 1)."
