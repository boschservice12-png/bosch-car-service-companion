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

# DSN-ul Doctrine conține `serverVersion` / `charset`, pe care libpq NU le
# cunoaște — pg_dump ar ieși cu „invalid URI query parameter". Le eliminăm,
# păstrând restul parametrilor (ex. sslmode).
pg_dsn() {
  printf '%s' "$1" | sed -E 's/([?&])(serverVersion|charset)=[^&]*/\1/g; s/&&+/\&/g; s/[?&]+$//; s/\?&/?/'
}

echo "[backup] pg_dump -> ${DEST}/db.sql.gz"
# Pas separat, nu `pg_dump | gzip`: într-un pipeline codul de ieșire e al
# gzip-ului, deci un pg_dump eșuat ar produce o arhivă GOALĂ dar validă, pe
# care `gzip -t` o acceptă. (`set -o pipefail` e activ aici, dar scriem explicit
# ca varianta din backup-cron.sh — care rulează sub `sh` — să arate la fel.)
pg_dump "$(pg_dsn "${DATABASE_URL}")" > "${DEST}/db.sql"
grep -q "PostgreSQL database dump complete" "${DEST}/db.sql" \
  || { echo "[backup] EROARE: dump trunchiat (lipsește marcajul de final)" >&2; exit 1; }
grep -qE "^(CREATE TABLE|COPY )" "${DEST}/db.sql" \
  || { echo "[backup] EROARE: dump fără schemă/date" >&2; exit 1; }
gzip -f "${DEST}/db.sql"
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
