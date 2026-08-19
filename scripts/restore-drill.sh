#!/usr/bin/env bash
# AUTOMATED monthly restore drill. Fetches the most recent backup FROM the
# bucket, restores it into a single-use database, verifies the result, cleans up.
#
# Why automated: "the restore was tested" is true on exactly the day someone
# tested it. Three months later nobody remembers, and a backup unverified for
# three months is an assumption again. The only way to keep the claim true is to
# re-verify it on a schedule.
#
# It restores from the OFF-BOX copy, not the local one: that exercises the exact
# path needed when the instance no longer exists.
#
# PRODUCTION IS NEVER TOUCHED. A separate database is created and dropped again.
#
# Cron (monthly, 1st, 06:00 UTC — after the backup and the off-box check):
#   0 6 1 * * /opt/bcss/scripts/restore-drill.sh >/dev/null 2>&1
#
# Variables (from /etc/bcss-monitoring.env):
#   COMPOSE_DIR             default /opt/bcss
#   HC_PING_URL_DRILL       healthchecks.io ping URL for THIS drill (the third one)
#   LOG_FILE                default /var/log/bcss-restore-drill.log
set -uo pipefail

ENV_FILE="${BCSS_MONITORING_ENV:-/etc/bcss-monitoring.env}"
if [ -r "${ENV_FILE}" ]; then set -a; . "${ENV_FILE}"; set +a; fi

COMPOSE_DIR="${COMPOSE_DIR:-/opt/bcss}"
LOG_FILE="${LOG_FILE:-/var/log/bcss-restore-drill.log}"
STAMP="$(date -u +%Y%m%d%H%M%S)"
DRILL_DB="drill_${STAMP}"
TS="$(date -u +%FT%TZ)"
REPORT=""

cd "${COMPOSE_DIR}" 2>/dev/null || { echo "[FAIL] cannot enter ${COMPOSE_DIR}" >&2; exit 1; }
COMPOSE=(docker compose --env-file .env.prod -f compose.prod.yaml)

# POSTGRES_USER / POSTGRES_PASSWORD / POSTGRES_DB live in .env.prod, not in the
# monitoring env file. They are needed to create the drill database and build
# the restore DSN. Cron runs as root, so a 0600 file is readable.
[ -r .env.prod ] || { echo "[FAIL] cannot read ${COMPOSE_DIR}/.env.prod (run as root)" >&2; exit 1; }
set -a; . ./.env.prod; set +a
: "${POSTGRES_PASSWORD:?[FAIL] POSTGRES_PASSWORD missing from .env.prod}"

log()  { REPORT="${REPORT}$1"$'\n'; echo "$1"; }
ping_hc() {
  [ -n "${HC_PING_URL_DRILL:-}" ] || return 0
  curl -fsS -m 10 --retry 3 --data-raw "${2:-}" "${HC_PING_URL_DRILL}${1:-}" >/dev/null 2>&1 || true
}

# The drill database is dropped NO MATTER WHAT — on failure and on interruption.
# Otherwise every failed run would leave an orphan database on the production disk.
cleanup() {
  "${COMPOSE[@]}" exec -T db psql -U "${POSTGRES_USER:-bcsc}" -d postgres \
    -c "DROP DATABASE IF EXISTS ${DRILL_DB};" >/dev/null 2>&1 || true
}
trap cleanup EXIT

finish() { # rc, summary
  printf '%s [rc=%s] %s\n%s\n' "${TS}" "$1" "$2" "${REPORT}" >> "${LOG_FILE}" 2>/dev/null
  if [ "$1" -eq 0 ]; then ping_hc "" "${REPORT}"; else ping_hc "/fail" "${REPORT}"; fi
  echo "$2"
  exit "$1"
}

ping_hc "/start" ""
log "=== restore drill ${TS} ==="

# --- 1. fetch the most recent backup from the off-box store -------------------
FETCH="$("${COMPOSE[@]}" run --rm --no-deps -T --entrypoint fetch-offsite.sh backup --latest 2>&1 < /dev/null)"
if [ $? -ne 0 ]; then
  log "${FETCH}"
  finish 1 "[FAIL] could not fetch the off-box backup"
fi
SRC="$(printf '%s' "${FETCH}" | grep -oE '/backups/restored/[0-9]{8}-[0-9]{6}' | tail -1)"
[ -n "${SRC}" ] || finish 1 "[FAIL] could not determine the fetched directory"
log "[ok]   fetched from off-box: ${SRC}"

# --- 2. single-use database ---------------------------------------------------
PROD_DB="${POSTGRES_DB:-bcsc}"
# Safety net: the drill name must never equal the production database.
[ "${DRILL_DB}" != "${PROD_DB}" ] || finish 1 "[FAIL] refusing: drill database name equals production"

"${COMPOSE[@]}" exec -T db psql -U "${POSTGRES_USER:-bcsc}" -d postgres \
  -c "CREATE DATABASE ${DRILL_DB} OWNER ${POSTGRES_USER:-bcsc};" >/dev/null 2>&1 \
  || finish 1 "[FAIL] could not create database ${DRILL_DB}"
log "[ok]   drill database created: ${DRILL_DB}"

# --- 3. restore ---------------------------------------------------------------
RESTORE="$("${COMPOSE[@]}" run --rm --no-deps -T \
  -e DATABASE_URL_RESTORE="postgresql://${POSTGRES_USER:-bcsc}:${POSTGRES_PASSWORD}@db:5432/${DRILL_DB}" \
  --entrypoint restore.sh backup "${SRC}" 2>&1 < /dev/null)"
if [ $? -ne 0 ]; then
  log "${RESTORE}"
  finish 1 "[FAIL] the restore failed"
fi
log "[ok]   restore completed"

# --- 4. verification ----------------------------------------------------------
q() { # database, query
  "${COMPOSE[@]}" exec -T db psql -U "${POSTGRES_USER:-bcsc}" -t -A -d "$1" -c "$2" 2>/dev/null | tr -d '\r' | head -1
}

# 4a. The table set must be identical to production. A truncated dump restores a
#     subset perfectly happily — which is why the exit code alone is not enough.
PROD_TABLES="$(q "${PROD_DB}"  "SELECT count(*) FROM information_schema.tables WHERE table_schema='public';")"
DRILL_TABLES="$(q "${DRILL_DB}" "SELECT count(*) FROM information_schema.tables WHERE table_schema='public';")"
if [ "${PROD_TABLES}" != "${DRILL_TABLES}" ]; then
  log "[FAIL] tables: production=${PROD_TABLES} restored=${DRILL_TABLES}"
  finish 1 "[FAIL] the table set does not match"
fi
log "[ok]   tables: ${DRILL_TABLES} (identical to production)"

# 4b. Migration history — without it the next deploy re-applies migrations.
PROD_MIG="$(q "${PROD_DB}"  "SELECT count(*) FROM doctrine_migration_versions;")"
DRILL_MIG="$(q "${DRILL_DB}" "SELECT count(*) FROM doctrine_migration_versions;")"
if [ "${PROD_MIG}" != "${DRILL_MIG}" ]; then
  log "[FAIL] migrations: production=${PROD_MIG} restored=${DRILL_MIG}"
  finish 1 "[FAIL] migration history does not match"
fi
log "[ok]   migrations: ${DRILL_MIG}"

# 4c. The data. We do NOT require equality: production moves on between the
#     backup and the drill, so a small difference is normal and expected. What is
#     NOT normal is production having rows while the restored copy is empty —
#     exactly the signature of an empty dump, the 5-11 August regression.
for T in users vehicles documents vehicle_deadlines; do
  P="$(q "${PROD_DB}" "SELECT count(*) FROM ${T};")"
  D="$(q "${DRILL_DB}" "SELECT count(*) FROM ${T};")"
  [ -n "${P}" ] && [ -n "${D}" ] || { log "[FAIL] cannot count ${T}"; finish 1 "[FAIL] query failed on ${T}"; }
  if [ "${P}" -gt 0 ] && [ "${D}" -eq 0 ]; then
    log "[FAIL] ${T}: production=${P} but restored=0 — empty backup?"
    finish 1 "[FAIL] ${T} is empty in the restored copy"
  fi
  log "[ok]   ${T}: production=${P} restored=${D}"
done

finish 0 "[ok] restore drill PASSED (${SRC} -> ${DRILL_DB}, cleaned up)"
