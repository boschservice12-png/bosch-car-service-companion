#!/bin/sh
# Backup scheduler container for PRODUCTION (the `backup` service in
# compose.prod.yaml). Once a day, at a fixed hour, it dumps PostgreSQL
# (pg_dump) AND the MinIO/S3 document store (mc mirror), with integrity checks,
# an off-box copy, and retention.
#
# Variables (from .env.prod + compose):
#   DATABASE_URL        (required) — pg_dump DSN
#   S3_ENDPOINT / S3_BUCKET / S3_KEY / S3_SECRET  (required) — the document store
#   BACKUP_DIR          (default /backups) — destination, a persistent volume
#   BACKUP_KEEP_DAYS    (default 14) — local backups older than this are deleted
#   BACKUP_HOUR         (default 3) — hour of the daily run, UTC
#   BACKUP_RUN_ON_START (default 0) — 1 also runs once at container start
#
#   --- OFF-BOX copy (Lightsail object storage, or any S3-compatible target) ---
#   OFFSITE_ENDPOINT   — e.g. https://s3.eu-central-1.amazonaws.com. If EMPTY,
#                        the off-box sync is skipped and that is logged loudly.
#   OFFSITE_BUCKET     — destination bucket name
#   OFFSITE_KEY / OFFSITE_SECRET — access credentials
#   OFFSITE_PREFIX     (default bcss) — prefix inside the bucket
#   OFFSITE_KEEP_DAYS  (default 30) — off-box retention, longer than local
#
# There is deliberately NO `set -e`: a failed run is logged, but the scheduler
# stays alive and tries again the next day.

BACKUP_DIR="${BACKUP_DIR:-/backups}"
KEEP_DAYS="${BACKUP_KEEP_DAYS:-14}"
HOUR="${BACKUP_HOUR:-3}"
OFFSITE_PREFIX="${OFFSITE_PREFIX:-bcss}"
OFFSITE_KEEP_DAYS="${OFFSITE_KEEP_DAYS:-30}"

# `mc --path` accepts only auto | on | off, which are not self-explanatory, so
# OFFSITE_LOOKUP also accepts friendlier names:
#   auto           -> auto   (correct in most cases)
#   dns / virtual  -> off    (virtual-host style: AWS S3, Lightsail)
#   path           -> on     (path style: MinIO)
mc_lookup() {
  case "${OFFSITE_LOOKUP:-auto}" in
    dns|virtual|off) echo "off" ;;
    path|on)         echo "on" ;;
    *)               echo "auto" ;;
  esac
}

# A backup sitting on the disk it protects is NOT a backup: if the instance is
# lost, the `backups` volume goes with it. This pushes a copy into a different
# failure domain. Its failure is not silenced: the local copy still exists, but
# the run exits non-zero so monitoring notices.
sync_offsite() {
  DEST="$1"
  if [ -z "${OFFSITE_ENDPOINT:-}" ]; then
    echo "[backup] WARNING: OFFSITE_ENDPOINT is not set — the backup exists ONLY on local disk."
    echo "[backup] WARNING: that does not qualify as disaster protection (see .env.prod.example)."
    return 0
  fi
  for v in OFFSITE_BUCKET OFFSITE_KEY OFFSITE_SECRET; do
    eval "val=\${$v:-}"
    [ -n "${val}" ] || { echo "[backup] ERROR: ${v} is missing, skipping off-box sync"; return 1; }
  done

  echo "[backup] off-box sync -> ${OFFSITE_ENDPOINT}/${OFFSITE_BUCKET}/${OFFSITE_PREFIX}"
  if ! mc alias set offsite "${OFFSITE_ENDPOINT}" "${OFFSITE_KEY}" "${OFFSITE_SECRET}" \
       --api S3v4 --path "$(mc_lookup)" >/dev/null 2>&1; then
    echo "[backup] ERROR: could not set the off-box alias (endpoint/credentials/OFFSITE_LOOKUP?)"; return 1
  fi
  # The bucket is created in the Lightsail console; the access key typically has
  # NO permission to create buckets, so this error is swallowed deliberately —
  # the mirror below decides whether the bucket is actually reachable.
  mc mb -p "offsite/${OFFSITE_BUCKET}" >/dev/null 2>&1 || true
  if ! mc mirror --overwrite "${DEST}" "offsite/${OFFSITE_BUCKET}/${OFFSITE_PREFIX}/$(basename "${DEST}")"; then
    echo "[backup] ERROR: off-box mirror failed"; return 1
  fi

  # Read back: the uploaded database archive must match the local size. Without
  # this we would only know the command ran, not that the data arrived.
  LOCAL_SIZE="$(wc -c < "${DEST}/db.sql.gz" | tr -d ' ')"
  REMOTE_SIZE="$(mc stat --json "offsite/${OFFSITE_BUCKET}/${OFFSITE_PREFIX}/$(basename "${DEST}")/db.sql.gz" 2>/dev/null \
    | sed -n 's/.*"size":\([0-9]*\).*/\1/p' | head -1)"
  if [ -z "${REMOTE_SIZE}" ] || [ "${LOCAL_SIZE}" != "${REMOTE_SIZE}" ]; then
    echo "[backup] ERROR: off-box verification failed (local=${LOCAL_SIZE}B, remote=${REMOTE_SIZE:-missing})"; return 1
  fi
  echo "[backup] off-box verification OK (${REMOTE_SIZE} B)"

  echo "[backup] off-box retention: ${OFFSITE_KEEP_DAYS} days"
  mc rm --recursive --force --older-than "${OFFSITE_KEEP_DAYS}d" \
    "offsite/${OFFSITE_BUCKET}/${OFFSITE_PREFIX}/" >/dev/null 2>&1 \
    || echo "[backup] WARNING: off-box retention did not run"
  return 0
}

# DATABASE_URL is a Doctrine DSN: libpq does NOT understand the `serverVersion`
# and `charset` parameters and pg_dump exits with "invalid URI query parameter".
# Strip those two before dumping, but keep EVERYTHING else (e.g. sslmode).
pg_dsn() {
  printf '%s' "$1" | sed -E 's/([?&])(serverVersion|charset)=[^&]*/\1/g; s/&&+/\&/g; s/[?&]+$//; s/\?&/?/'
}

run_backup() {
  TS="$(date +%Y%m%d-%H%M%S)"
  DEST="${BACKUP_DIR}/${TS}"
  mkdir -p "${DEST}" || { echo "[backup] ERROR: cannot create ${DEST}"; return 1; }

  # CAUTION, this used to fail SILENTLY: the exit code of `pg_dump … | gzip > f`
  # is gzip's, which is 0 even when pg_dump died. The result was a 20-byte,
  # EMPTY but perfectly valid gzip — which `gzip -t` happily accepts. Hence:
  # a separate step, its own exit code, and content assertions.
  echo "[backup] pg_dump -> ${DEST}/db.sql.gz"
  if ! pg_dump "$(pg_dsn "${DATABASE_URL}")" > "${DEST}/db.sql"; then
    echo "[backup] ERROR: pg_dump failed"; rm -f "${DEST}/db.sql"; return 1
  fi
  # A real dump contains the closing marker; without it the dump is truncated.
  if ! grep -q "PostgreSQL database dump complete" "${DEST}/db.sql"; then
    echo "[backup] ERROR: dump is truncated (closing marker missing)"; rm -f "${DEST}/db.sql"; return 1
  fi
  if ! grep -qE "^(CREATE TABLE|COPY )" "${DEST}/db.sql"; then
    echo "[backup] ERROR: dump contains no schema or data"; rm -f "${DEST}/db.sql"; return 1
  fi
  if ! gzip -f "${DEST}/db.sql"; then
    echo "[backup] ERROR: compressing the dump failed"; return 1
  fi
  if ! gzip -t "${DEST}/db.sql.gz"; then
    echo "[backup] ERROR: the database archive is corrupt"; return 1
  fi
  echo "[backup] database archive: $(wc -c < "${DEST}/db.sql.gz" | tr -d ' ') B"

  echo "[backup] saving documents (mc mirror ${S3_BUCKET})"
  if ! mc alias set bkp "${S3_ENDPOINT}" "${S3_KEY}" "${S3_SECRET}" >/dev/null 2>&1; then
    echo "[backup] ERROR: could not set the MinIO alias"; return 1
  fi
  # For an EMPTY bucket, `mc mirror` does not create the target directory at
  # all, so `tar` would fail — taking the WHOLE backup down with it, including
  # the database dump that already succeeded. A pilot with no documents yet (or
  # an emptied bucket) would then produce a failed backup every single night.
  # Create it up front: an empty bucket yields an empty but valid archive.
  mkdir -p "${DEST}/documents"
  if ! mc mirror --overwrite "bkp/${S3_BUCKET}" "${DEST}/documents"; then
    echo "[backup] ERROR: mc mirror failed"; return 1
  fi
  DOC_COUNT="$(find "${DEST}/documents" -type f | wc -l | tr -d ' ')"
  echo "[backup] documents saved: ${DOC_COUNT}"
  if [ "${DOC_COUNT}" = "0" ]; then
    echo "[backup] WARNING: the document bucket is EMPTY (${S3_BUCKET}). If that is unexpected, check storage."
  fi
  if ! tar -czf "${DEST}/documents.tar.gz" -C "${DEST}" documents; then
    echo "[backup] ERROR: building the document archive failed"; return 1
  fi
  rm -rf "${DEST}/documents"
  gzip -t "${DEST}/documents.tar.gz" || { echo "[backup] ERROR: the document archive is corrupt"; return 1; }

  # Off-box copy BEFORE retention: if it fails, the local copy still exists but
  # the run exits non-zero so the alert fires.
  OFFSITE_RC=0
  sync_offsite "${DEST}" || OFFSITE_RC=1

  echo "[backup] local retention: ${KEEP_DAYS} days"
  find "${BACKUP_DIR}" -mindepth 1 -maxdepth 1 -type d -mtime "+${KEEP_DAYS}" -exec rm -rf {} +

  echo "[backup] done: ${DEST}"
  ls -lh "${DEST}"
  return "${OFFSITE_RC}"
}

# Single immediate run (for manual use): runs once, then exits.
if [ "${BACKUP_ONESHOT:-0}" = "1" ]; then
  echo "[backup] one-shot run…"
  run_backup
  exit $?
fi

if [ "${BACKUP_RUN_ON_START:-0}" = "1" ]; then
  echo "[backup] start-up run…"
  run_backup || echo "[backup] the start-up run failed (continuing with the schedule)"
fi

echo "[backup] scheduler started; daily run at ${HOUR}:00 UTC."
while true; do
  NOW="$(date +%s)"
  TARGET="$(date -d "today ${HOUR}:00" +%s 2>/dev/null || echo "")"
  if [ -z "${TARGET}" ]; then
    # Fallback when `date -d` is unavailable: sleep 24 hours.
    sleep 86400
  else
    [ "${TARGET}" -le "${NOW}" ] && TARGET=$((TARGET + 86400))
    SLEEP=$((TARGET - NOW))
    echo "[backup] next run in ${SLEEP} s."
    sleep "${SLEEP}"
  fi
  run_backup || echo "[backup] the run failed (retrying tomorrow)"
done
