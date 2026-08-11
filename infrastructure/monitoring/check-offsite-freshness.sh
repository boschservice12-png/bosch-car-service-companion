#!/usr/bin/env bash
# Verifică dacă în depozitul OFF-BOX există un backup suficient de recent.
#
# Gaura pe care o astupă: `healthcheck.sh` se uită doar la backupurile LOCALE.
# Dacă cheile Lightsail expiră, sunt rotite sau bucketul e redenumit, mentințele
# locale continuă să reușească, prospețimea locală rămâne verde — și copia
# off-box se oprește în tăcere. Exact scenariul împotriva căruia există copia
# off-box. Rulează O DATĂ pe zi (după fereastra de backup), nu la 5 minute:
# pornește un container, deci e mult mai scump decât un curl.
#
# Variabile (din /etc/bcss-monitoring.env):
#   COMPOSE_DIR          — implicit /opt/bcss
#   OFFSITE_MAX_AGE_H    — implicit 26
#   HC_PING_URL_OFFSITE  — ping healthchecks.io pentru ACEASTĂ verificare (alt URL!)
#   LOG_FILE             — implicit /var/log/bcss-offsite-check.log
set -uo pipefail

# Vezi cron-healthcheck.sh: citim configurația aici ca intrarea de crontab să
# încapă comod pe o singură linie.
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

finish() { # rc, mesaj
  printf '%s [rc=%s] %s\n' "${TS}" "$1" "$2" >> "${LOG_FILE}" 2>/dev/null
  echo "$2"
  if [ "$1" -eq 0 ]; then ping_hc "" "$2"; else ping_hc "/fail" "$2"; fi
  exit "$1"
}

ping_hc "/start" ""

cd "${COMPOSE_DIR}" 2>/dev/null || finish 1 "[FAIL] nu pot intra în ${COMPOSE_DIR}"

# --no-deps: nu pornim baza doar ca să listăm un bucket.
LISTING="$(docker compose --env-file .env.prod -f compose.prod.yaml run --rm --no-deps -T \
             --entrypoint fetch-offsite.sh backup --list 2>&1)" \
  || finish 1 "[FAIL] listarea off-box a eșuat:\n${LISTING}"

LATEST="$(printf '%s' "${LISTING}" | grep -oE '[0-9]{8}-[0-9]{6}' | sort | tail -1)"
[ -n "${LATEST}" ] || finish 1 "[FAIL] niciun backup off-box găsit:\n${LISTING}"

# Numele directorului e chiar timestamp-ul UTC al rulării: YYYYmmdd-HHMMSS.
LATEST_EPOCH="$(date -u -d "${LATEST:0:8} ${LATEST:9:2}:${LATEST:11:2}:${LATEST:13:2}" +%s 2>/dev/null)"
[ -n "${LATEST_EPOCH}" ] || finish 1 "[FAIL] timestamp off-box neinterpretabil: ${LATEST}"

AGE_H=$(( ( $(date -u +%s) - LATEST_EPOCH ) / 3600 ))
if [ "${AGE_H}" -le "${MAX_AGE_H}" ]; then
  finish 0 "[ok]   backup off-box recent: ${LATEST} (vechime ${AGE_H}h)"
else
  finish 1 "[FAIL] cel mai recent backup off-box e vechi de ${AGE_H}h (prag ${MAX_AGE_H}h): ${LATEST}"
fi
