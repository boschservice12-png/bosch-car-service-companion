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

# 1b) Verificările NECRITICE din readiness. Ele întorc 200 chiar și picate — asta
# e intenționat (instanța rămâne servibilă), dar înseamnă că `curl -fsS` de mai
# sus NU le vede. Fără bucata asta, un ClamAV mort e complet tăcut: readiness
# rămâne „verde" pentru monitorizare, în timp ce documentele încărcate nu mai
# avansează niciodată din coadă.
READY_JSON="$(curl -fsS --max-time 10 "${BASE}/api/health/ready" 2>/dev/null || true)"
probe_status() { # json, nume-verificare
  printf '%s' "$1" | grep -o "\"$2\":{\"status\":\"[a-z]*\"" | sed 's/.*"\([a-z]*\)"$/\1/'
}
if [ -n "${READY_JSON}" ]; then
  for probe in scanner messenger; do
    ST="$(probe_status "${READY_JSON}" "${probe}")"
    if [ "${ST}" = "ok" ]; then
      echo "[ok]   readiness/${probe}"
    elif [ -z "${ST}" ]; then
      echo "[FAIL] readiness/${probe}: lipsește din răspuns (versiune veche de backend?)" >&2
      FAIL=1
    else
      echo "[FAIL] readiness/${probe}: ${ST} (necritic, dar procesarea e blocată)" >&2
      FAIL=1
    fi
  done
else
  echo "[FAIL] readiness: răspuns necitibil" >&2
  FAIL=1
fi

# 2) Spațiu pe disc — documentele și baza cresc; plin = incident.
USED_PCT="$(df --output=pcent "${DISK_PATH:-/}" | tail -1 | tr -dc '0-9')"
if [ "${USED_PCT}" -le "${DISK_MAX_PCT:-85}" ]; then
  echo "[ok]   disc ${USED_PCT}% folosit"
else
  echo "[FAIL] disc ${USED_PCT}% folosit (> ${DISK_MAX_PCT:-85}%)" >&2
  FAIL=1
fi

# 3) Prospețimea ȘI substanța backupului (dacă BACKUP_DIR e setat).
if [ -n "${BACKUP_DIR:-}" ]; then
  # Doar directoarele cu NUME de timestamp (YYYYmmdd-HHMMSS). Altfel `sort |
  # tail -1` alegea alfabetic, deci `restaurate/` — directorul de lucru al lui
  # fetch-offsite.sh — trecea drept „ultimul backup", iar prospețimea raportată
  # era de fapt data ultimei descărcări manuale.
  LAST="$(find "${BACKUP_DIR}" -mindepth 1 -maxdepth 1 -type d 2>/dev/null \
          | grep -E '/[0-9]{8}-[0-9]{6}$' | sort | tail -1)"

  if [ -z "${LAST}" ]; then
    echo "[FAIL] niciun backup în ${BACKUP_DIR}" >&2
    FAIL=1
  elif [ -z "$(find "${LAST}" -maxdepth 0 -mmin "-$(( ${BACKUP_MAX_AGE_H:-26} * 60 ))")" ]; then
    echo "[FAIL] niciun backup în ultimele ${BACKUP_MAX_AGE_H:-26}h (${BACKUP_DIR})" >&2
    FAIL=1
  else
    # „Există un fișier" nu înseamnă „există un backup". Timp de șapte nopți
    # `db.sql.gz` a avut 20 de octeți — un gzip GOL, dar perfect valid — pentru
    # că pg_dump pica, iar `gzip -t` accepta rezultatul. Verificăm deci și
    # DIMENSIUNEA, nu doar prezența și vechimea.
    DUMP="${LAST}/db.sql.gz"
    MIN_BYTES="${BACKUP_MIN_DUMP_BYTES:-1024}"
    if [ ! -f "${DUMP}" ]; then
      echo "[FAIL] backupul ${LAST} nu conține db.sql.gz" >&2
      FAIL=1
    else
      SIZE="$(wc -c < "${DUMP}" | tr -d ' ')"
      if [ "${SIZE}" -lt "${MIN_BYTES}" ]; then
        echo "[FAIL] db.sql.gz din ${LAST} are doar ${SIZE}B (< ${MIN_BYTES}B) — dump gol?" >&2
        FAIL=1
      else
        echo "[ok]   backup recent: $(basename "${LAST}") (db.sql.gz ${SIZE}B)"
      fi
    fi
  fi
fi

exit "${FAIL}"
