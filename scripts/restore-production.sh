#!/usr/bin/env bash
# RESTAURARE PESTE PRODUCȚIE. Singurul script din repo care DISTRUGE date reale.
#
#   CONFIRM=RESTAUREZ-PRODUCTIA ./scripts/restore-production.sh /backups/restaurate/20260811-030000
#
# De ce script și nu o listă de pași într-un document: procedura are cinci pași
# care trebuie făcuți în ordine, iar cel care o execută o face în cel mai prost
# moment posibil — producția e căzută, e noapte, iar o comandă sărită înseamnă
# fie o restaurare pe jumătate, fie pierderea stării curente.
#
# Ce face, în ordine:
#   1. BACKUP al stării CURENTE (înainte de a o distruge)
#   2. oprește scriitorii (backend, worker, scheduler); baza și MinIO rămân sus
#   3. închide conexiunile rămase și golește schema
#   4. restaurează din backupul indicat
#   5. repornește și verifică
#
# Documentele NU se ating implicit. Vezi RESTORE_DOCUMENTS mai jos.
set -euo pipefail

SRC="${1:-}"
APP_DIR="${APP_DIR:-/opt/bcss}"
cd "${APP_DIR}"
COMPOSE=(docker compose --env-file .env.prod -f compose.prod.yaml)
WRITERS=(backend worker scheduler)

say() { echo; echo "── $* ──"; }
die() { echo "EROARE: $*" >&2; exit 1; }

# --- Garduri -----------------------------------------------------------------
[ -n "${SRC}" ] || die "Utilizare: CONFIRM=RESTAUREZ-PRODUCTIA $0 <director-backup>|--latest"
[ "${CONFIRM:-}" = "RESTAUREZ-PRODUCTIA" ] || cat >&2 <<EOF
EROARE: confirmare lipsă.

Scriptul ȘTERGE baza de date de producție și o înlocuiește cu conținutul din
   ${SRC}

Dacă chiar asta vreți:
   CONFIRM=RESTAUREZ-PRODUCTIA $0 ${SRC}
EOF
[ "${CONFIRM:-}" = "RESTAUREZ-PRODUCTIA" ] || exit 1

[ -f .env.prod ] || die "${APP_DIR}/.env.prod lipsește."
set -a; . ./.env.prod; set +a
: "${POSTGRES_PASSWORD:?lipsește din .env.prod}"
PROD_DB="${POSTGRES_DB:-bcsc}"
PGUSER_="${POSTGRES_USER:-bcsc}"

psql_() { "${COMPOSE[@]}" exec -T db psql -U "${PGUSER_}" "$@"; }

# `--latest` = adu singur cea mai recentă copie din depozitul off-box, apoi
# restaureaz-o. Ăsta e cazul real de urgență: instanța e stricată, iar cel care
# repară nu trebuie să caute manual timestamp-uri în bucket.
if [ "${SRC}" = "--latest" ]; then
  say "aduc cel mai recent backup din depozitul off-box"
  FETCH_OUT="$("${COMPOSE[@]}" run --rm --no-deps -T --entrypoint fetch-offsite.sh backup --latest < /dev/null 2>&1)" \
    || { echo "${FETCH_OUT}" >&2; die "nu am putut aduce backupul off-box"; }
  SRC="$(printf '%s' "${FETCH_OUT}" | grep -oE '/backups/restaurate/[0-9]{8}-[0-9]{6}' | tail -1)"
  [ -n "${SRC}" ] || die "nu am putut determina directorul adus din bucket"
  echo "adus: ${SRC}"
fi

# Backupul indicat trebuie să existe ȘI să conțină un dump real. Fără asta am
# putea goli producția și abia apoi descoperi că arhiva e goală.
"${COMPOSE[@]}" run --rm --no-deps -T --entrypoint sh backup -c "
  set -e
  [ -f '${SRC}/db.sql.gz' ] || { echo 'lipsește ${SRC}/db.sql.gz'; exit 1; }
  gzip -t '${SRC}/db.sql.gz'
  gunzip -c '${SRC}/db.sql.gz' | grep -q 'PostgreSQL database dump complete'
" < /dev/null >/dev/null 2>&1 \
  || die "${SRC} nu conține un dump complet și valid. NU am atins producția."

say "0. sursă validată: ${SRC}"
echo "ținta: baza '${PROD_DB}' din producție"

# --- 1. plasa de siguranță ----------------------------------------------------
say "1. backup al stării CURENTE (înainte de a o distruge)"
# Dacă se restaurează din greșeală backupul greșit, ăsta e singurul drum înapoi.
#
# Merge într-un PREFIX SEPARAT în bucket, nu în rotația normală. E o copie a unei
# stări despre care știm că e stricată — de-aia o restaurăm peste. Dacă ar ateriza
# în rotația obișnuită, ar deveni „cel mai recent backup", adică exact ce ar lua
# un `--latest` ulterior sau drill-ul lunar. (Prins de propriul drill pe
# 2026-08-11: după o restaurare, drill-ul a raportat corect
# `vehicle_deadlines: producție=4 dar restaurat=0`, pentru că cel mai recent
# backup off-box era instantaneul stării stricate.)
"${COMPOSE[@]}" run --rm -T \
  -e BACKUP_ONESHOT=1 \
  -e OFFSITE_PREFIX="${OFFSITE_PREFIX:-bcss}-pre-restore" \
  backup < /dev/null \
  || die "backupul de siguranță a eșuat — refuz să continui fără el"
echo "(copia off-box a stării dinaintea restaurării: prefix '${OFFSITE_PREFIX:-bcss}-pre-restore')"

# --- 2. oprim scriitorii ------------------------------------------------------
say "2. opresc scriitorii: ${WRITERS[*]}"
# Baza și MinIO rămân pornite (avem nevoie de ele). Frontendurile rămân sus și
# vor da erori de API — acceptabil: producția e oricum indisponibilă în timpul
# unei restaurări, iar o pagină care se încarcă e mai bună decât una moartă.
"${COMPOSE[@]}" stop "${WRITERS[@]}"

# --- 3. golim schema ----------------------------------------------------------
say "3. închid conexiunile rămase"
# Golirea schemei o face `restore.sh` singur. Aici doar ne asigurăm că nu mai e
# nimeni conectat: restore.sh REFUZĂ să golească o bază cu conexiuni active
# (protecția lui împotriva ștergerii schemei sub o aplicație pornită), iar un
# `stop` de container poate lăsa o conexiune muribundă în urmă.
psql_ -d postgres -c \
  "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname='${PROD_DB}' AND pid <> pg_backend_pid();" >/dev/null
echo "conexiuni închise"

# --- 4. restaurăm -------------------------------------------------------------
say "4. restaurez din ${SRC}"
"${COMPOSE[@]}" run --rm --no-deps -T \
  -e DATABASE_URL_RESTORE="postgresql://${PGUSER_}:${POSTGRES_PASSWORD}@db:5432/${PROD_DB}" \
  ${RESTORE_DOCUMENTS:+-e S3_ENDPOINT_RESTORE=${S3_ENDPOINT}} \
  ${RESTORE_DOCUMENTS:+-e S3_BUCKET_RESTORE=${S3_BUCKET}} \
  ${RESTORE_DOCUMENTS:+-e S3_KEY_RESTORE=${S3_KEY}} \
  ${RESTORE_DOCUMENTS:+-e S3_SECRET_RESTORE=${S3_SECRET}} \
  --entrypoint restore.sh backup "${SRC}" < /dev/null \
  || die "restaurarea a eșuat. Scriitorii sunt încă OPRIȚI — schema e goală sau parțială. NU reporniți până nu restaurați cu succes."

if [ -z "${RESTORE_DOCUMENTS:-}" ]; then
  echo
  echo "NOTĂ: documentele din bucket NU au fost atinse (doar baza de date)."
  echo "Pentru a restaura și documentele: RESTORE_DOCUMENTS=1 $0 ${SRC}"
  echo "Atenție: mc mirror suprascrie și adaugă, dar NU șterge obiectele care"
  echo "există live și lipsesc din backup."
fi

# --- 5. repornim și verificăm -------------------------------------------------
say "5. repornesc și verific"
"${COMPOSE[@]}" up -d "${WRITERS[@]}"

MIG="$(psql_ -t -A -d "${PROD_DB}" -c 'SELECT count(*) FROM doctrine_migration_versions;' | tr -d '\r')"
echo "migrații restaurate: ${MIG}"

BASE_URL="${DEPLOY_BASE_URL:-https://app.bcss.ro}"
for i in $(seq 1 24); do
  curl -fsS --max-time 5 "${BASE_URL}/api/health" >/dev/null 2>&1 && { echo "liveness OK după ~$((i*5))s"; break; }
  [ "${i}" -eq 24 ] && die "/api/health nu răspunde după 120s — verificați 'logs backend'"
  sleep 5
done
BASE_URL="${BASE_URL}" NONCRITICAL_MODE=warn bash infrastructure/monitoring/healthcheck.sh

say "restaurare încheiată din ${SRC}"
echo "Backupul stării dinaintea restaurării a rămas în /backups (pasul 1)."
