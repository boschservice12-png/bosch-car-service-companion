#!/usr/bin/env bash
# Restaurare din backup — companion scriptabil pentru drill-ul din restore.md.
# Un backup fără restaurare testată NU este un backup: rulați acest script lunar
# pe un mediu izolat (bază goală, storage gol) și consemnați RTO/RPO.
#
# Utilizare:
#   DATABASE_URL_RESTORE=postgresql://… STORAGE_DIR=/app/var/storage \
#     ./restore.sh /backups/20260720-031500
#
#   # restaurare în bucket S3/MinIO (layout de PRODUCȚIE):
#   DATABASE_URL_RESTORE=postgresql://… \
#     S3_ENDPOINT_RESTORE=http://minio:9000 S3_BUCKET_RESTORE=bcsc-documents \
#     S3_KEY_RESTORE=… S3_SECRET_RESTORE=… ./restore.sh /backups/20260720-031500
#
# Variabile:
#   DATABASE_URL_RESTORE  (obligatoriu) — DSN țintă pentru psql (NU producția!)
#   STORAGE_DIR           (implicit /app/var/storage) — unde se extrag documentele
#                         la restaurarea pe disc local (STORAGE_DRIVER=local)
#   S3_*_RESTORE          — dacă S3_ENDPOINT_RESTORE e setat, documentele se
#                         restaurează în bucket cu `mc mirror` (STORAGE_DRIVER=s3)
#   ALLOW_DB_ONLY_RESTORE=1 — acceptă explicit un backup fără documente
set -euo pipefail

SRC="${1:-}"
if [ -z "${SRC}" ] || [ ! -d "${SRC}" ]; then
  echo "Utilizare: DATABASE_URL_RESTORE=… ./restore.sh <director-backup>" >&2
  exit 2
fi
if [ -z "${DATABASE_URL_RESTORE:-}" ]; then
  echo "[restore] EROARE: DATABASE_URL_RESTORE nesetat (refuz să ghicesc ținta)." >&2
  exit 2
fi

STORAGE="${STORAGE_DIR:-/app/var/storage}"
DB_ARCHIVE="${SRC}/db.sql.gz"

# Cele DOUĂ layout-uri de backup din acest repo — trebuie acceptate amândouă:
#   documents.tar.gz  — scris de backup-cron.sh (PRODUCȚIE, `mc mirror` din bucket)
#   storage.tar.gz    — scris de backup.sh      (driver `local`, tar din disc)
# Până acum scriptul căuta DOAR storage.tar.gz, deci pe un backup de producție
# raporta „doar baza a fost restaurată" și ieșea cu 0 — adică pierdea TOATE
# documentele fără să eșueze. Un restore care reușește pe jumătate e mai
# periculos decât unul care crapă.
if [ -f "${SRC}/documents.tar.gz" ]; then
  DOC_ARCHIVE="${SRC}/documents.tar.gz"
  DOC_LAYOUT="documents"   # arhiva conține directorul `documents/`
elif [ -f "${SRC}/storage.tar.gz" ]; then
  DOC_ARCHIVE="${SRC}/storage.tar.gz"
  DOC_LAYOUT="flat"        # arhiva conține direct conținutul storage-ului
else
  DOC_ARCHIVE=""
  DOC_LAYOUT=""
fi

# DSN-ul Doctrine conține `serverVersion` / `charset`, necunoscute de libpq —
# psql ar ieși cu „invalid URI query parameter". Le eliminăm, păstrând restul.
pg_dsn() {
  printf '%s' "$1" | sed -E 's/([?&])(serverVersion|charset)=[^&]*/\1/g; s/&&+/\&/g; s/[?&]+$//; s/\?&/?/'
}

# 1) Integritatea arhivelor înainte de a atinge ținta — o arhivă coruptă oprește
#    restaurarea devreme, nu la jumătate.
echo "[restore] verific integritatea arhivelor…"
[ -f "${DB_ARCHIVE}" ] || { echo "[restore] lipsește ${DB_ARCHIVE}" >&2; exit 1; }
gzip -t "${DB_ARCHIVE}"

# `gzip -t` trece și pe o arhivă GOALĂ (20 de octeți) — exact ce producea un
# pg_dump eșuat înainte de corecția din backup-cron.sh. Deci verificăm CONȚINUTUL,
# nu doar integritatea: altfel „restaurăm" cu succes o bază complet goală.
if ! gunzip -c "${DB_ARCHIVE}" | grep -q "PostgreSQL database dump complete"; then
  echo "[restore] EROARE: ${DB_ARCHIVE} nu e un dump complet (gol sau trunchiat)." >&2
  echo "[restore] Ținta NU a fost atinsă. Verificați log-ul backupului care l-a produs." >&2
  exit 1
fi

if [ -n "${DOC_ARCHIVE}" ]; then
  gzip -t "${DOC_ARCHIVE}"
  echo "[restore] arhivă documente: $(basename "${DOC_ARCHIVE}") (layout: ${DOC_LAYOUT})"
elif [ "${ALLOW_DB_ONLY_RESTORE:-0}" = "1" ]; then
  echo "[restore] AVERTISMENT: backup fără documente, acceptat explicit (ALLOW_DB_ONLY_RESTORE=1)." >&2
else
  echo "[restore] EROARE: ${SRC} nu conține nici documents.tar.gz, nici storage.tar.gz." >&2
  echo "[restore] Baza NU a fost atinsă. O restaurare doar-bază lasă înregistrări de" >&2
  echo "[restore] documente fără fișierele lor. Dacă chiar asta vreți: ALLOW_DB_ONLY_RESTORE=1." >&2
  exit 1
fi

# 2) Baza de date.
TARGET_DSN="$(pg_dsn "${DATABASE_URL_RESTORE}")"

# Golim schema țintei ÎNAINTE de restaurare. Dumpul conține `CREATE TABLE` fără
# `DROP`, deci pe o bază care are deja schema, psql se oprea la
#   ERROR: relation "application_settings" already exists
# Cu ON_ERROR_STOP asta e un eșec CURAT (nu lasă baza pe jumătate), dar înseamnă
# că restaurarea peste o bază existentă pur și simplu nu funcționa, iar
# operatorul trebuia să știe să ruleze un DROP SCHEMA manual înainte — exact
# genul de pas care se uită la 3 dimineața. Acum scriptul îl face singur.
#
# Ținta e ÎNTOTDEAUNA explicită (DATABASE_URL_RESTORE e obligatoriu, scriptul
# refuză să ghicească), deci golim doar ce ni s-a cerut explicit să rescriem.
# RESTORE_KEEP_SCHEMA=1 sare peste, pentru o țintă despre care știți că e goală.
if [ "${RESTORE_KEEP_SCHEMA:-0}" != "1" ]; then
  # Dacă altcineva e conectat la țintă, e aproape sigur o bază VIE cu scriitori
  # activi. Nu golim schema sub picioarele unei aplicații care rulează.
  OTHERS="$(psql -tAX "${TARGET_DSN}" -c \
    "SELECT count(*) FROM pg_stat_activity WHERE datname = current_database() AND pid <> pg_backend_pid();" 2>/dev/null || echo 0)"
  if [ "${OTHERS:-0}" -gt 0 ]; then
    echo "[restore] EROARE: ținta are ${OTHERS} conexiuni active — pare o bază VIE." >&2
    echo "[restore] Nu golesc schema sub o aplicație pornită. Pentru producție folosiți:" >&2
    echo "[restore]   CONFIRM=RESTAUREZ-PRODUCTIA ./scripts/restore-production.sh <backup>" >&2
    echo "[restore] (oprește întâi backend/worker/scheduler). Ținta NU a fost atinsă." >&2
    exit 1
  fi
  echo "[restore] golesc schema țintei…"
  psql --quiet --set ON_ERROR_STOP=on "${TARGET_DSN}" \
    -c "DROP SCHEMA IF EXISTS public CASCADE; CREATE SCHEMA public;"
fi

echo "[restore] restaurez baza de date -> DATABASE_URL_RESTORE"
gunzip -c "${DB_ARCHIVE}" | psql --quiet --set ON_ERROR_STOP=on "${TARGET_DSN}"

# 3) Documentele — pe disc local sau înapoi în bucket, după cum e configurat.
if [ -n "${DOC_ARCHIVE}" ]; then
  if [ -n "${S3_ENDPOINT_RESTORE:-}" ]; then
    : "${S3_BUCKET_RESTORE:?[restore] S3_BUCKET_RESTORE lipsește}"
    : "${S3_KEY_RESTORE:?[restore] S3_KEY_RESTORE lipsește}"
    : "${S3_SECRET_RESTORE:?[restore] S3_SECRET_RESTORE lipsește}"
    command -v mc >/dev/null 2>&1 || { echo "[restore] EROARE: mc lipsește (folosiți imaginea de backup)." >&2; exit 1; }

    TMP="$(mktemp -d)"
    trap 'rm -rf "${TMP}"' EXIT
    tar -xzf "${DOC_ARCHIVE}" -C "${TMP}"
    # Normalizăm cele două layout-uri la un singur director sursă.
    if [ "${DOC_LAYOUT}" = "documents" ]; then
      SRC_DIR="${TMP}/documents"
    else
      SRC_DIR="${TMP}"
    fi

    echo "[restore] restaurez documentele -> ${S3_ENDPOINT_RESTORE}/${S3_BUCKET_RESTORE}"
    mc alias set rst "${S3_ENDPOINT_RESTORE}" "${S3_KEY_RESTORE}" "${S3_SECRET_RESTORE}" >/dev/null
    mc mb -p "rst/${S3_BUCKET_RESTORE}" >/dev/null 2>&1 || true
    mc anonymous set none "rst/${S3_BUCKET_RESTORE}" >/dev/null 2>&1 || true
    mc mirror --overwrite "${SRC_DIR}" "rst/${S3_BUCKET_RESTORE}"

    RESTORED="$(mc ls --recursive "rst/${S3_BUCKET_RESTORE}" | wc -l | tr -d ' ')"
    echo "[restore] obiecte în bucket după restaurare: ${RESTORED}"
  else
    echo "[restore] restaurez documentele -> ${STORAGE}"
    mkdir -p "${STORAGE}"
    if [ "${DOC_LAYOUT}" = "documents" ]; then
      # Arhiva de producție are prefixul `documents/`; îl aplatizăm ca storage-ul
      # local să arate identic cu cel produs de backup.sh.
      tar -xzf "${DOC_ARCHIVE}" -C "${STORAGE}" --strip-components=1
    else
      tar -xzf "${DOC_ARCHIVE}" -C "${STORAGE}"
    fi
    echo "[restore] fișiere restaurate: $(find "${STORAGE}" -type f | wc -l | tr -d ' ')"
  fi
fi

echo "[restore] gata. Verificări post-restaurare (vezi restore.md):"
echo "  - doctrine:migrations:status  (migrări la zi)"
echo "  - GET /api/health/ready  =>  200 (readiness profund)"
echo "  - un client de test își vede vehiculele + descarcă un document"
echo "  - consemnați data, RTO (durata), RPO (pierderea maximă)"
