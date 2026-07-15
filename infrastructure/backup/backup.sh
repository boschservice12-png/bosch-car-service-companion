#!/usr/bin/env bash
# Backup zilnic — bază de date + object storage.
# De rulat prin cron/scheduler; restaurarea trebuie TESTATĂ periodic (vezi restore.md).
set -euo pipefail

TS="$(date +%Y%m%d-%H%M%S)"
DEST="${BACKUP_DIR:-/backups}/${TS}"
mkdir -p "${DEST}"

echo "[backup] pg_dump -> ${DEST}/db.sql.gz"
pg_dump "${DATABASE_URL}" | gzip > "${DEST}/db.sql.gz"

echo "[backup] object storage sync -> ${DEST}/storage"
# mc mirror <alias>/<bucket> "${DEST}/storage"   # se configurează cu MinIO client

echo "[backup] gata: ${DEST}"
# Alertă la eșec: hook în sistemul de monitorizare (backup eșuat = alertă critică).
