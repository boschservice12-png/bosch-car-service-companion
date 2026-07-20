#!/usr/bin/env bash
# Restaurare din backup — companion scriptabil pentru drill-ul din restore.md.
# Un backup fără restaurare testată NU este un backup: rulați acest script lunar
# pe un mediu izolat (bază goală, storage gol) și consemnați RTO/RPO.
#
# Utilizare:
#   DATABASE_URL_RESTORE=postgresql://… STORAGE_DIR=/app/var/storage \
#     ./restore.sh /backups/20260720-031500
#
# Variabile:
#   DATABASE_URL_RESTORE  (obligatoriu) — DSN țintă pentru psql (NU producția!)
#   STORAGE_DIR           (implicit /app/var/storage) — unde se extrag documentele
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
STORAGE_ARCHIVE="${SRC}/storage.tar.gz"

# 1) Integritatea arhivelor înainte de a atinge ținta — o arhivă coruptă oprește
#    restaurarea devreme, nu la jumătate.
echo "[restore] verific integritatea arhivelor…"
[ -f "${DB_ARCHIVE}" ] || { echo "[restore] lipsește ${DB_ARCHIVE}" >&2; exit 1; }
gzip -t "${DB_ARCHIVE}"
if [ -f "${STORAGE_ARCHIVE}" ]; then
  gzip -t "${STORAGE_ARCHIVE}"
fi

# 2) Baza de date.
echo "[restore] restaurez baza de date -> DATABASE_URL_RESTORE"
gunzip -c "${DB_ARCHIVE}" | psql "${DATABASE_URL_RESTORE}"

# 3) Documentele (storage local). La driver S3 documentele se restaurează în
#    bucket cu `mc mirror` — vezi restore.md.
if [ -f "${STORAGE_ARCHIVE}" ]; then
  echo "[restore] restaurez documentele -> ${STORAGE}"
  mkdir -p "${STORAGE}"
  tar -xzf "${STORAGE_ARCHIVE}" -C "${STORAGE}"
else
  echo "[restore] AVERTISMENT: backupul nu conține storage.tar.gz — doar baza a fost restaurată." >&2
fi

echo "[restore] gata. Verificări post-restaurare (vezi restore.md):"
echo "  - doctrine:migrations:status  (migrări la zi)"
echo "  - GET /api/health/ready  =>  200 (readiness profund)"
echo "  - un client de test își vede vehiculele + descarcă un document"
echo "  - consemnați data, RTO (durata), RPO (pierderea maximă)"
