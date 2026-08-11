#!/usr/bin/env bash
# Health check for external monitoring (cron / uptime checker).
# Exits 0 only if ALL checks pass; otherwise it writes the reason to stderr and
# exits non-zero — the alerting system hooks onto the exit code.
#
# Variables:
#   BASE_URL              (default https://localhost) — the public root
#   BACKUP_DIR            (optional) — if set, checks the age and size of the
#                         most recent backup
#   BACKUP_MAX_AGE_H      (default 26) — alert if the last backup is older
#   BACKUP_MIN_DUMP_BYTES (default 1024) — alert if db.sql.gz is smaller
#   DISK_PATH             (default /) — partition checked for free space
#   DISK_MAX_PCT          (default 85) — alert above this usage percentage
#   NONCRITICAL_MODE      (default fail) — `warn` downgrades non-critical
#                         readiness probes to warnings; used by the deploy gate
set -uo pipefail

BASE="${BASE_URL:-https://localhost}"
FAIL=0

check() { # name, command…
  local name="$1"; shift
  if "$@" > /dev/null 2>&1; then
    echo "[ok]   ${name}"
  else
    echo "[FAIL] ${name}" >&2
    FAIL=1
  fi
}

# 1) The application responds and its dependencies (DB, storage) work.
check "liveness  GET /api/health"        curl -fsS --max-time 10 "${BASE}/api/health"
check "readiness GET /api/health/ready"  curl -fsS --max-time 10 "${BASE}/api/health/ready"

# 1b) The NON-CRITICAL readiness probes. They return 200 even when failing — that
# is intentional (the instance stays servable), but it means the `curl -fsS`
# above does NOT see them. Without this block a dead ClamAV is completely
# silent: readiness stays "green" for monitoring while uploaded documents never
# advance out of the queue.
READY_JSON="$(curl -fsS --max-time 10 "${BASE}/api/health/ready" 2>/dev/null || true)"
probe_status() { # json, check-name
  printf '%s' "$1" | grep -o "\"$2\":{\"status\":\"[a-z]*\"" | sed 's/.*"\([a-z]*\)"$/\1/'
}
if [ -n "${READY_JSON}" ]; then
  for probe in scanner messenger; do
    ST="$(probe_status "${READY_JSON}" "${probe}")"
    if [ "${ST}" = "ok" ]; then
      echo "[ok]   readiness/${probe}"
    elif [ -z "${ST}" ]; then
      echo "[FAIL] readiness/${probe}: missing from the response (old backend version?)" >&2
      FAIL=1
    elif [ "${NONCRITICAL_MODE:-fail}" = "warn" ]; then
      # `warn` mode is for the deploy gate. Non-critical checks can be red
      # TEMPORARILY right after a restart — ClamAV, for instance, needs minutes
      # to load its signature databases. Failing a deploy over that means
      # reporting failure for a successful delivery. The five-minute cron
      # catches them anyway if they stay red.
      echo "[warn] readiness/${probe}: ${ST} (non-critical — may just be start-up)"
    else
      echo "[FAIL] readiness/${probe}: ${ST} (non-critical, but processing is stalled)" >&2
      FAIL=1
    fi
  done
else
  echo "[FAIL] readiness: unreadable response" >&2
  FAIL=1
fi

# 2) Disk space — documents and the database grow; full = incident.
USED_PCT="$(df --output=pcent "${DISK_PATH:-/}" | tail -1 | tr -dc '0-9')"
if [ "${USED_PCT}" -le "${DISK_MAX_PCT:-85}" ]; then
  echo "[ok]   disk ${USED_PCT}% used"
else
  echo "[FAIL] disk ${USED_PCT}% used (> ${DISK_MAX_PCT:-85}%)" >&2
  FAIL=1
fi

# 3) Backup freshness AND substance (when BACKUP_DIR is set).
if [ -n "${BACKUP_DIR:-}" ]; then
  # Only directories whose NAME is a timestamp (YYYYmmdd-HHMMSS). Otherwise
  # `sort | tail -1` picked alphabetically, so `restored/` — fetch-offsite.sh's
  # working directory — was treated as "the latest backup", and the reported
  # freshness was actually the date of the last manual download.
  LAST="$(find "${BACKUP_DIR}" -mindepth 1 -maxdepth 1 -type d 2>/dev/null \
          | grep -E '/[0-9]{8}-[0-9]{6}$' | sort | tail -1)"

  if [ -z "${LAST}" ]; then
    echo "[FAIL] no backup in ${BACKUP_DIR}" >&2
    FAIL=1
  elif [ -z "$(find "${LAST}" -maxdepth 0 -mmin "-$(( ${BACKUP_MAX_AGE_H:-26} * 60 ))")" ]; then
    echo "[FAIL] no backup in the last ${BACKUP_MAX_AGE_H:-26}h (${BACKUP_DIR})" >&2
    FAIL=1
  else
    # "A file exists" is not the same as "a backup exists". For seven nights
    # db.sql.gz was 20 bytes — an EMPTY but perfectly valid gzip — because
    # pg_dump was failing and `gzip -t` accepted the result. So check the SIZE
    # too, not just presence and age.
    DUMP="${LAST}/db.sql.gz"
    MIN_BYTES="${BACKUP_MIN_DUMP_BYTES:-1024}"
    if [ ! -f "${DUMP}" ]; then
      echo "[FAIL] backup ${LAST} contains no db.sql.gz" >&2
      FAIL=1
    else
      SIZE="$(wc -c < "${DUMP}" | tr -d ' ')"
      if [ "${SIZE}" -lt "${MIN_BYTES}" ]; then
        echo "[FAIL] db.sql.gz in ${LAST} is only ${SIZE}B (< ${MIN_BYTES}B) — empty dump?" >&2
        FAIL=1
      else
        echo "[ok]   recent backup: $(basename "${LAST}") (db.sql.gz ${SIZE}B)"
      fi
    fi
  fi
fi

exit "${FAIL}"
