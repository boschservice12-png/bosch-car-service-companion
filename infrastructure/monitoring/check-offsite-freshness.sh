#!/usr/bin/env bash
# Checks that a sufficiently recent backup exists in the OFF-BOX store.
#
# The gap this closes: `healthcheck.sh` only looks at LOCAL backups. If the
# Lightsail keys expire, are rotated, or the bucket is renamed, local backups
# keep succeeding, local freshness stays green — and the off-box copy stops
# silently. That is precisely the scenario the off-box copy exists to survive.
# Runs ONCE a day (after the backup window), not every five minutes: it starts a
# container, so it is far more expensive than a curl.
#
# Variables (from /etc/bcss-monitoring.env):
#   COMPOSE_DIR          default /opt/bcss
#   OFFSITE_MAX_AGE_H    default 26
#   HC_PING_URL_OFFSITE  healthchecks.io ping for THIS check (a different URL!)
#   LOG_FILE             default /var/log/bcss-offsite-check.log
set -uo pipefail

# See cron-healthcheck.sh: the configuration is read here so the crontab entry
# fits comfortably on one line.
ENV_FILE="${BCSS_MONITORING_ENV:-/etc/bcss-monitoring.env}"
if [ -r "${ENV_FILE}" ]; then set -a; . "${ENV_FILE}"; set +a; fi

COMPOSE_DIR="${COMPOSE_DIR:-/opt/bcss}"
MAX_AGE_H="${OFFSITE_MAX_AGE_H:-26}"
LOG_FILE="${LOG_FILE:-/var/log/bcss-offsite-check.log}"
TS="$(date -u +%FT%TZ)"

ping_hc() {
  [ -n "${HC_PING_URL_OFFSITE:-}" ] || return 0
  curl -fsS -m 10 --retry 3 --data-raw "${2:-}" "${HC_PING_URL_OFFSITE}${1:-}" >/dev/null 2>&1 || true
}

finish() { # rc, message
  printf '%s [rc=%s] %s\n' "${TS}" "$1" "$2" >> "${LOG_FILE}" 2>/dev/null
  echo "$2"
  if [ "$1" -eq 0 ]; then ping_hc "" "$2"; else ping_hc "/fail" "$2"; fi
  exit "$1"
}

ping_hc "/start" ""

cd "${COMPOSE_DIR}" 2>/dev/null || finish 1 "[FAIL] cannot enter ${COMPOSE_DIR}"

# --no-deps: do not start the database just to list a bucket.
LISTING="$(docker compose --env-file .env.prod -f compose.prod.yaml run --rm --no-deps -T \
             --entrypoint fetch-offsite.sh backup --list 2>&1)" \
  || finish 1 "[FAIL] listing the off-box store failed:\n${LISTING}"

LATEST="$(printf '%s' "${LISTING}" | grep -oE '[0-9]{8}-[0-9]{6}' | sort | tail -1)"
[ -n "${LATEST}" ] || finish 1 "[FAIL] no off-box backup found:\n${LISTING}"

# The directory name is the UTC timestamp of the run: YYYYmmdd-HHMMSS.
LATEST_EPOCH="$(date -u -d "${LATEST:0:8} ${LATEST:9:2}:${LATEST:11:2}:${LATEST:13:2}" +%s 2>/dev/null)"
[ -n "${LATEST_EPOCH}" ] || finish 1 "[FAIL] unparseable off-box timestamp: ${LATEST}"

AGE_H=$(( ( $(date -u +%s) - LATEST_EPOCH ) / 3600 ))
if [ "${AGE_H}" -le "${MAX_AGE_H}" ]; then
  finish 0 "[ok]   recent off-box backup: ${LATEST} (age ${AGE_H}h)"
else
  finish 1 "[FAIL] the most recent off-box backup is ${AGE_H}h old (threshold ${MAX_AGE_H}h): ${LATEST}"
fi
