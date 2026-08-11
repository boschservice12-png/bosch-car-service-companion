#!/bin/sh
# Scheduler for the application's periodic tasks (the `scheduler` service in
# compose.prod.yaml). It runs on the backend image because it needs
# `bin/console`, not just a PostgreSQL client like the `backup` service does.
#
# One task for now: `app:gdpr:purge`. The system holds vehicle records belonging
# to private individuals, and the retention policy does not apply itself — the
# command existed from the start, but nothing was running it. See
# docs/security/data-retention-policy.md.
#
# Variables:
#   GDPR_PURGE_HOUR    (default 4) — hour of the daily run, UTC. Deliberately
#                      AFTER the backup (03:00): save first, then delete
#                      irreversibly.
#   GDPR_PURGE_ENABLED (default 1) — 0 disables purging entirely
#
# No `set -e`: a failed run is logged, but the scheduler stays alive and retries
# the next day.

HOUR="${GDPR_PURGE_HOUR:-4}"

if [ "${GDPR_PURGE_ENABLED:-1}" != "1" ]; then
  echo "[scheduler] GDPR_PURGE_ENABLED=0 — purging is DISABLED."
  echo "[scheduler] The retention policy is not being applied. Intentional?"
fi

run_purge() {
  if [ "${GDPR_PURGE_ENABLED:-1}" != "1" ]; then
    return 0
  fi
  echo "[scheduler] $(date -u +%FT%TZ) running app:gdpr:purge…"
  if php bin/console app:gdpr:purge --no-interaction; then
    echo "[scheduler] app:gdpr:purge succeeded."
    return 0
  fi
  echo "[scheduler] ERROR: app:gdpr:purge failed (retrying tomorrow)."
  return 1
}

# Single run, for manual triggering:
#   docker compose … run --rm -e SCHEDULER_ONESHOT=1 scheduler
if [ "${SCHEDULER_ONESHOT:-0}" = "1" ]; then
  run_purge
  exit $?
fi

echo "[scheduler] started; app:gdpr:purge daily at ${HOUR}:00 UTC."
while true; do
  NOW="$(date +%s)"
  TARGET="$(date -d "today ${HOUR}:00" +%s 2>/dev/null || echo "")"
  if [ -z "${TARGET}" ]; then
    sleep 86400
  else
    [ "${TARGET}" -le "${NOW}" ] && TARGET=$((TARGET + 86400))
    SLEEP=$((TARGET - NOW))
    echo "[scheduler] next run in ${SLEEP} s."
    sleep "${SLEEP}"
  fi
  run_purge || true
done
