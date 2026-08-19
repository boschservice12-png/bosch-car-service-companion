#!/usr/bin/env bash
# Fetch a backup FROM the off-box store (Lightsail object storage, or any
# S3-compatible target) onto local disk, so it can be handed to `restore.sh`.
#
# This is the scenario the off-box copy exists for: the instance is gone, and so
# is the `backups` volume. Without this script the remote backups would only be
# recoverable by hand — at precisely the moment nobody wants to improvise.
#
# Usage:
#   ./fetch-offsite.sh --list                 # what exists remotely
#   ./fetch-offsite.sh --latest               # fetch the most recent
#   ./fetch-offsite.sh 20260804-031500        # fetch a specific one
#
# Variables (same as the backup):
#   OFFSITE_ENDPOINT / OFFSITE_BUCKET / OFFSITE_KEY / OFFSITE_SECRET
#   OFFSITE_PREFIX  (default bcss)
#   OFFSITE_LOOKUP  (default auto; dns|path if the provider requires it)
#   FETCH_DIR       (default /backups/restored) — download destination
set -euo pipefail

PREFIX="${OFFSITE_PREFIX:-bcss}"
FETCH_DIR="${FETCH_DIR:-/backups/restored}"

for v in OFFSITE_ENDPOINT OFFSITE_BUCKET OFFSITE_KEY OFFSITE_SECRET; do
  eval "val=\${$v:-}"
  [ -n "${val}" ] || { echo "[fetch] ERROR: ${v} is not set." >&2; exit 2; }
done

# `mc --path` accepts only auto|on|off; we also accept the descriptive names,
# the same way backup-cron.sh does (dns/virtual = AWS/Lightsail, path = MinIO).
case "${OFFSITE_LOOKUP:-auto}" in
  dns|virtual|off) LOOKUP=off ;;
  path|on)         LOOKUP=on ;;
  *)               LOOKUP=auto ;;
esac

mc alias set offsite "${OFFSITE_ENDPOINT}" "${OFFSITE_KEY}" "${OFFSITE_SECRET}" \
  --api S3v4 --path "${LOOKUP}" >/dev/null

BASE="offsite/${OFFSITE_BUCKET}/${PREFIX}"

list_backups() {
  # Directory names are timestamps (YYYYmmdd-HHMMSS), so lexicographic sorting
  # is also chronological.
  mc ls "${BASE}/" 2>/dev/null | awk '{print $NF}' | tr -d '/' | grep -E '^[0-9]{8}-[0-9]{6}$' | sort
}

ARG="${1:-}"
case "${ARG}" in
  --list|"")
    echo "[fetch] backups available in ${BASE}:"
    list_backups | sed 's/^/  /'
    [ -n "${ARG}" ] || { echo; echo "Pick one: ./fetch-offsite.sh <timestamp>  (or --latest)"; }
    exit 0
    ;;
  --latest)
    TS="$(list_backups | tail -1)"
    [ -n "${TS}" ] || { echo "[fetch] ERROR: no backup exists in the off-box store." >&2; exit 1; }
    ;;
  *)
    TS="${ARG}"
    ;;
esac

DEST="${FETCH_DIR}/${TS}"
echo "[fetch] fetching ${BASE}/${TS} -> ${DEST}"
mkdir -p "${DEST}"
mc mirror --overwrite "${BASE}/${TS}" "${DEST}"

# Verify what we downloaded BEFORE handing it to the restore: corruption in
# transit must be caught here, not in the middle of a restore.
echo "[fetch] verifying the downloaded archives…"
[ -f "${DEST}/db.sql.gz" ] || { echo "[fetch] ERROR: db.sql.gz is missing" >&2; exit 1; }
gzip -t "${DEST}/db.sql.gz"
if ! gunzip -c "${DEST}/db.sql.gz" | grep -q "PostgreSQL database dump complete"; then
  echo "[fetch] ERROR: db.sql.gz is not a complete dump (empty or truncated)." >&2
  exit 1
fi
for a in "${DEST}/documents.tar.gz" "${DEST}/storage.tar.gz"; do
  [ -f "${a}" ] && gzip -t "${a}"
done

echo "[fetch] done: ${DEST}"
ls -lh "${DEST}"
echo
echo "Restore with:"
echo "  DATABASE_URL_RESTORE=… restore.sh ${DEST}"
