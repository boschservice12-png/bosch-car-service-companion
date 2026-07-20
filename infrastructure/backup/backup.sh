#!/usr/bin/env bash
# Backup zilnic — bază de date + documentele încărcate (storage local).
# De rulat prin cron (vezi ../monitoring/monitoring.md); restaurarea se
# TESTEAZĂ periodic după procedura din restore.md.
#
# Variabile:
#   DATABASE_URL      (obligatoriu) — DSN PostgreSQL pentru pg_dump
#   STORAGE_DIR       (implicit /app/var/storage) — documentele aplicației
#   BACKUP_DIR        (implicit /backups) — destinația arhivelor
#   BACKUP_KEEP_DAYS  (implicit 14) — retenția; arhivele mai vechi se șterg
set -euo pipefail

TS="$(date +%Y%m%d-%H%M%S)"
ROOT="${BACKUP_DIR:-/backups}"
DEST="${ROOT}/${TS}"
STORAGE="${STORAGE_DIR:-/app/var/storage}"
KEEP_DAYS="${BACKUP_KEEP_DAYS:-14}"
mkdir -p "${DEST}"

echo "[backup] pg_dump -> ${DEST}/db.sql.gz"
pg_dump "${DATABASE_URL}" | gzip > "${DEST}/db.sql.gz"
# Verificare de integritate: o arhivă coruptă e mai rea decât una lipsă.
gzip -t "${DEST}/db.sql.gz"

if [ -d "${STORAGE}" ]; then
  echo "[backup] documente -> ${DEST}/storage.tar.gz"
  tar -czf "${DEST}/storage.tar.gz" -C "${STORAGE}" .
  gzip -t "${DEST}/storage.tar.gz"
else
  echo "[backup] AVERTISMENT: ${STORAGE} nu există — se salvează doar baza de date." >&2
fi

echo "[backup] retenție: se păstrează ${KEEP_DAYS} zile"
find "${ROOT}" -mindepth 1 -maxdepth 1 -type d -mtime "+${KEEP_DAYS}" -exec rm -rf {} +

echo "[backup] gata: ${DEST}"
ls -lh "${DEST}"
# La eșec scriptul iese nenul (set -e) — cronul/monitorizarea trebuie să
# alerteze pe exit code diferit de 0 ȘI pe „ultimul backup mai vechi de 24h"
# (vezi healthcheck.sh, verificarea de prospețime).
