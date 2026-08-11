#!/usr/bin/env bash
# Drill de restaurare AUTOMAT, lunar. Aduce cel mai recent backup DIN bucket,
# îl restaurează într-o bază de unică folosință, verifică rezultatul, apoi curăță.
#
# De ce automat: „restaurarea a fost testată" e adevărat exact în ziua în care
# cineva a testat-o. Peste trei luni nimeni nu-și mai amintește, iar un backup
# neverificat de trei luni e din nou o presupunere. Singurul mod de a păstra
# afirmația adevărată e s-o reverifici singur, periodic.
#
# Restaurează din copia OFF-BOX, nu din cea locală: asta exercită exact drumul
# de care ai nevoie când instanța nu mai există.
#
# PRODUCȚIA NU E ATINSĂ. Se creează o bază separată, se șterge la final.
#
# Cron (lunar, ziua 1, 06:00 UTC — după backup și după verificarea off-box):
#   0 6 1 * * /opt/bcss/scripts/restore-drill.sh >/dev/null 2>&1
#
# Variabile (din /etc/bcss-monitoring.env):
#   COMPOSE_DIR             implicit /opt/bcss
#   HC_PING_URL_DRILL       ping healthchecks.io pentru ACEST drill (al treilea URL)
#   LOG_FILE                implicit /var/log/bcss-restore-drill.log
set -uo pipefail

ENV_FILE="${BCSS_MONITORING_ENV:-/etc/bcss-monitoring.env}"
if [ -r "${ENV_FILE}" ]; then set -a; . "${ENV_FILE}"; set +a; fi

COMPOSE_DIR="${COMPOSE_DIR:-/opt/bcss}"
LOG_FILE="${LOG_FILE:-/var/log/bcss-restore-drill.log}"
STAMP="$(date -u +%Y%m%d%H%M%S)"
DRILL_DB="drill_${STAMP}"
TS="$(date -u +%FT%TZ)"
REPORT=""

cd "${COMPOSE_DIR}" 2>/dev/null || { echo "[FAIL] nu pot intra în ${COMPOSE_DIR}" >&2; exit 1; }
COMPOSE=(docker compose --env-file .env.prod -f compose.prod.yaml)

# POSTGRES_USER / POSTGRES_PASSWORD / POSTGRES_DB stau în .env.prod, nu în
# fișierul de monitorizare. Avem nevoie de ele ca să creăm baza de drill și să
# construim DSN-ul de restaurare. Cronul rulează ca root, deci poate citi 0600.
[ -r .env.prod ] || { echo "[FAIL] nu pot citi ${COMPOSE_DIR}/.env.prod (rulați ca root)" >&2; exit 1; }
set -a; . ./.env.prod; set +a
: "${POSTGRES_PASSWORD:?[FAIL] POSTGRES_PASSWORD lipsește din .env.prod}"

log()  { REPORT="${REPORT}$1"$'\n'; echo "$1"; }
ping_hc() {
  [ -n "${HC_PING_URL_DRILL:-}" ] || return 0
  curl -fsS -m 10 --retry 3 --data-raw "${2:-}" "${HC_PING_URL_DRILL}${1:-}" >/dev/null 2>&1 || true
}

# Baza de drill se șterge ORICUM — și la eșec, și la întrerupere. Altfel fiecare
# rulare eșuată ar lăsa în urmă o bază orfană pe discul producției.
cleanup() {
  "${COMPOSE[@]}" exec -T db psql -U "${POSTGRES_USER:-bcsc}" -d postgres \
    -c "DROP DATABASE IF EXISTS ${DRILL_DB};" >/dev/null 2>&1 || true
}
trap cleanup EXIT

finish() { # rc, rezumat
  printf '%s [rc=%s] %s\n%s\n' "${TS}" "$1" "$2" "${REPORT}" >> "${LOG_FILE}" 2>/dev/null
  if [ "$1" -eq 0 ]; then ping_hc "" "${REPORT}"; else ping_hc "/fail" "${REPORT}"; fi
  echo "$2"
  exit "$1"
}

ping_hc "/start" ""
log "=== drill de restaurare ${TS} ==="

# --- 1. aducem cel mai recent backup din depozitul off-box --------------------
FETCH="$("${COMPOSE[@]}" run --rm --no-deps -T --entrypoint fetch-offsite.sh backup --latest 2>&1 < /dev/null)"
if [ $? -ne 0 ]; then
  log "${FETCH}"
  finish 1 "[FAIL] nu am putut aduce backupul off-box"
fi
SRC="$(printf '%s' "${FETCH}" | grep -oE '/backups/restaurate/[0-9]{8}-[0-9]{6}' | tail -1)"
[ -n "${SRC}" ] || finish 1 "[FAIL] nu am putut determina directorul adus"
log "[ok]   adus din off-box: ${SRC}"

# --- 2. bază de unică folosință ----------------------------------------------
PROD_DB="${POSTGRES_DB:-bcsc}"
# Plasă de siguranță: numele de drill nu are voie să coincidă cu producția.
[ "${DRILL_DB}" != "${PROD_DB}" ] || finish 1 "[FAIL] refuz: baza de drill coincide cu producția"

"${COMPOSE[@]}" exec -T db psql -U "${POSTGRES_USER:-bcsc}" -d postgres \
  -c "CREATE DATABASE ${DRILL_DB} OWNER ${POSTGRES_USER:-bcsc};" >/dev/null 2>&1 \
  || finish 1 "[FAIL] nu am putut crea baza ${DRILL_DB}"
log "[ok]   bază de drill creată: ${DRILL_DB}"

# --- 3. restaurare ------------------------------------------------------------
RESTORE="$("${COMPOSE[@]}" run --rm --no-deps -T \
  -e DATABASE_URL_RESTORE="postgresql://${POSTGRES_USER:-bcsc}:${POSTGRES_PASSWORD}@db:5432/${DRILL_DB}" \
  --entrypoint restore.sh backup "${SRC}" 2>&1 < /dev/null)"
if [ $? -ne 0 ]; then
  log "${RESTORE}"
  finish 1 "[FAIL] restaurarea a eșuat"
fi
log "[ok]   restaurare încheiată"

# --- 4. verificări ------------------------------------------------------------
q() { # bază, interogare
  "${COMPOSE[@]}" exec -T db psql -U "${POSTGRES_USER:-bcsc}" -t -A -d "$1" -c "$2" 2>/dev/null | tr -d '\r' | head -1
}

# 4a. setul de tabele trebuie să fie identic cu al producției. Un dump trunchiat
#     restaurează „cu succes" un subset — de asta nu ne uităm doar la exit code.
PROD_TABLES="$(q "${PROD_DB}"  "SELECT count(*) FROM information_schema.tables WHERE table_schema='public';")"
DRILL_TABLES="$(q "${DRILL_DB}" "SELECT count(*) FROM information_schema.tables WHERE table_schema='public';")"
if [ "${PROD_TABLES}" != "${DRILL_TABLES}" ]; then
  log "[FAIL] tabele: producție=${PROD_TABLES} restaurat=${DRILL_TABLES}"
  finish 1 "[FAIL] setul de tabele nu se potrivește"
fi
log "[ok]   tabele: ${DRILL_TABLES} (identic cu producția)"

# 4b. istoricul migrațiilor — fără el, următorul deploy reaplică migrații.
PROD_MIG="$(q "${PROD_DB}"  "SELECT count(*) FROM doctrine_migration_versions;")"
DRILL_MIG="$(q "${DRILL_DB}" "SELECT count(*) FROM doctrine_migration_versions;")"
if [ "${PROD_MIG}" != "${DRILL_MIG}" ]; then
  log "[FAIL] migrații: producție=${PROD_MIG} restaurat=${DRILL_MIG}"
  finish 1 "[FAIL] istoricul migrațiilor nu se potrivește"
fi
log "[ok]   migrații: ${DRILL_MIG}"

# 4c. Datele. NU cerem egalitate: producția evoluează între momentul backupului
#     și cel al drill-ului, deci o diferență mică e normală și așteptată. Ce NU
#     e normal e ca producția să aibă rânduri, iar copia restaurată să fie goală
#     — exact semnătura unui dump gol, care e regresia din 05-11 august.
for T in users vehicles documents vehicle_deadlines; do
  P="$(q "${PROD_DB}" "SELECT count(*) FROM ${T};")"
  D="$(q "${DRILL_DB}" "SELECT count(*) FROM ${T};")"
  [ -n "${P}" ] && [ -n "${D}" ] || { log "[FAIL] nu pot număra ${T}"; finish 1 "[FAIL] interogare eșuată pe ${T}"; }
  if [ "${P}" -gt 0 ] && [ "${D}" -eq 0 ]; then
    log "[FAIL] ${T}: producție=${P} dar restaurat=0 — backup gol?"
    finish 1 "[FAIL] ${T} e gol în copia restaurată"
  fi
  log "[ok]   ${T}: producție=${P} restaurat=${D}"
done

finish 0 "[ok] drill de restaurare TRECUT (${SRC} -> ${DRILL_DB}, curățat)"
