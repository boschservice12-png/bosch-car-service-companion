#!/usr/bin/env bash
# Verificare de sănătate pentru monitorizare externă (cron / uptime-checker).
# Iese cu 0 doar dacă TOATE verificările trec; altfel scrie motivul pe stderr
# și iese nenul — sistemul de alertare se leagă de exit code.
#
# Variabile:
#   BASE_URL          (implicit https://localhost) — rădăcina publică
#   BACKUP_DIR        (opțional) — dacă e setat, verifică vârsta ultimului backup
#   BACKUP_MAX_AGE_H  (implicit 26) — alertă dacă ultimul backup e mai vechi
#   DISK_PATH         (implicit /) — partiția verificată pentru spațiu
#   DISK_MAX_PCT      (implicit 85) — alertă peste acest procent de ocupare
set -uo pipefail

BASE="${BASE_URL:-https://localhost}"
FAIL=0

check() { # nume, comandă…
  local name="$1"; shift
  if "$@" > /dev/null 2>&1; then
    echo "[ok]   ${name}"
  else
    echo "[FAIL] ${name}" >&2
    FAIL=1
  fi
}

# 1) Aplicația răspunde și dependențele (DB, storage) sunt funcționale.
check "liveness  GET /api/health"        curl -fsS --max-time 10 "${BASE}/api/health"
check "readiness GET /api/health/ready"  curl -fsS --max-time 10 "${BASE}/api/health/ready"

# 2) Spațiu pe disc — documentele și baza cresc; plin = incident.
USED_PCT="$(df --output=pcent "${DISK_PATH:-/}" | tail -1 | tr -dc '0-9')"
if [ "${USED_PCT}" -le "${DISK_MAX_PCT:-85}" ]; then
  echo "[ok]   disc ${USED_PCT}% folosit"
else
  echo "[FAIL] disc ${USED_PCT}% folosit (> ${DISK_MAX_PCT:-85}%)" >&2
  FAIL=1
fi

# 3) Prospețimea backupului (dacă BACKUP_DIR e setat).
if [ -n "${BACKUP_DIR:-}" ]; then
  LAST="$(find "${BACKUP_DIR}" -mindepth 1 -maxdepth 1 -type d | sort | tail -1)"
  if [ -n "${LAST}" ] && [ -n "$(find "${LAST}" -maxdepth 0 -mmin "-$(( ${BACKUP_MAX_AGE_H:-26} * 60 ))")" ]; then
    echo "[ok]   backup recent: ${LAST}"
  else
    echo "[FAIL] niciun backup în ultimele ${BACKUP_MAX_AGE_H:-26}h (${BACKUP_DIR})" >&2
    FAIL=1
  fi
fi

exit "${FAIL}"
