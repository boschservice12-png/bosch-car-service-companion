#!/bin/sh
# Backup ütemező konténer PRODUKCIÓHOZ (compose.prod.yaml `backup` szolgáltatás).
# Naponta egyszer, egy fix órában menti a PostgreSQL-t (pg_dump) ÉS a MinIO/S3
# dokumentumtárat (mc mirror), integritás-ellenőrzéssel és retencióval.
#
# Változók (a .env.prod-ból + a compose-ból):
#   DATABASE_URL       (kötelező) — pg_dump DSN
#   S3_ENDPOINT/S3_BUCKET/S3_KEY/S3_SECRET  (kötelező) — a MinIO/S3 tár
#   BACKUP_DIR         (alap /backups) — a mentések célja (perzisztens volume)
#   BACKUP_KEEP_DAYS   (alap 14) — ennél régebbi mentések törlődnek
#   BACKUP_HOUR        (alap 3) — a napi futás órája (UTC)
#   BACKUP_RUN_ON_START (alap 0) — 1 esetén induláskor is fut egyszer
#
# Szándékosan NINCS `set -e`: egy sikertelen futás naplózódik, de az ütemező
# tovább él (a következő nap újrapróbál).

BACKUP_DIR="${BACKUP_DIR:-/backups}"
KEEP_DAYS="${BACKUP_KEEP_DAYS:-14}"
HOUR="${BACKUP_HOUR:-3}"

run_backup() {
  TS="$(date +%Y%m%d-%H%M%S)"
  DEST="${BACKUP_DIR}/${TS}"
  mkdir -p "${DEST}" || { echo "[backup] HIBA: nem hozható létre ${DEST}"; return 1; }

  echo "[backup] pg_dump -> ${DEST}/db.sql.gz"
  if ! pg_dump "${DATABASE_URL}" | gzip > "${DEST}/db.sql.gz"; then
    echo "[backup] HIBA: pg_dump sikertelen"; return 1
  fi
  if ! gzip -t "${DEST}/db.sql.gz"; then
    echo "[backup] HIBA: a DB-archívum sérült"; return 1
  fi

  echo "[backup] dokumentumok mentése (mc mirror ${S3_BUCKET})"
  if ! mc alias set bkp "${S3_ENDPOINT}" "${S3_KEY}" "${S3_SECRET}" >/dev/null 2>&1; then
    echo "[backup] HIBA: nem sikerült a MinIO alias"; return 1
  fi
  if ! mc mirror --overwrite "bkp/${S3_BUCKET}" "${DEST}/documents"; then
    echo "[backup] HIBA: mc mirror sikertelen"; return 1
  fi
  if ! tar -czf "${DEST}/documents.tar.gz" -C "${DEST}" documents; then
    echo "[backup] HIBA: dokumentum-archívum sikertelen"; return 1
  fi
  rm -rf "${DEST}/documents"
  gzip -t "${DEST}/documents.tar.gz" || { echo "[backup] HIBA: dokumentum-archívum sérült"; return 1; }

  echo "[backup] retenció: ${KEEP_DAYS} nap"
  find "${BACKUP_DIR}" -mindepth 1 -maxdepth 1 -type d -mtime "+${KEEP_DAYS}" -exec rm -rf {} +

  echo "[backup] kész: ${DEST}"
  ls -lh "${DEST}"
  return 0
}

# Egyszeri, azonnali mentés (kézi futtatáshoz): fut egyszer, majd kilép.
if [ "${BACKUP_ONESHOT:-0}" = "1" ]; then
  echo "[backup] egyszeri futtatás (oneshot)…"
  run_backup
  exit $?
fi

if [ "${BACKUP_RUN_ON_START:-0}" = "1" ]; then
  echo "[backup] indulási futtatás…"
  run_backup || echo "[backup] az indulási futtatás sikertelen (folytatom az ütemezést)"
fi

echo "[backup] ütemező elindult; napi futás ${HOUR}:00 UTC-kor."
while true; do
  NOW="$(date +%s)"
  TARGET="$(date -d "today ${HOUR}:00" +%s 2>/dev/null || echo "")"
  if [ -z "${TARGET}" ]; then
    # tartalék, ha a `date -d` nem elérhető: 24 órát alszik
    sleep 86400
  else
    [ "${TARGET}" -le "${NOW}" ] && TARGET=$((TARGET + 86400))
    SLEEP=$((TARGET - NOW))
    echo "[backup] következő futás ${SLEEP} mp múlva."
    sleep "${SLEEP}"
  fi
  run_backup || echo "[backup] a futás sikertelen volt (a következő napon újrapróbálom)"
done
