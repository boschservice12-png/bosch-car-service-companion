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
#   --- OFF-BOX másolat (Lightsail object storage / bármely S3-kompatibilis) ---
#   OFFSITE_ENDPOINT   — pl. https://s3.eu-central-1.amazonaws.com vagy a
#                        Lightsail bucket endpointja. Ha ÜRES, az off-box
#                        szinkron kimarad (és ezt hangosan naplózzuk).
#   OFFSITE_BUCKET     — a cél bucket neve
#   OFFSITE_KEY/OFFSITE_SECRET — hozzáférési kulcsok
#   OFFSITE_PREFIX     (alap bcss) — prefix a bucketen belül
#   OFFSITE_KEEP_DAYS  (alap 30) — off-box retenció (hosszabb, mint a helyi)
#
# Szándékosan NINCS `set -e`: egy sikertelen futás naplózódik, de az ütemező
# tovább él (a következő nap újrapróbál).

BACKUP_DIR="${BACKUP_DIR:-/backups}"
KEEP_DAYS="${BACKUP_KEEP_DAYS:-14}"
HOUR="${BACKUP_HOUR:-3}"
OFFSITE_PREFIX="${OFFSITE_PREFIX:-bcss}"
OFFSITE_KEEP_DAYS="${OFFSITE_KEEP_DAYS:-30}"

# Az `mc --path` kapcsoló értékei: auto | on | off. Ezek viszont nem beszédesek,
# ezért az OFFSITE_LOOKUP a barátságosabb neveket is elfogadja:
#   auto            -> auto   (a legtöbb esetben jó)
#   dns / virtual   -> off    (virtual-host stílus: AWS S3, Lightsail)
#   path            -> on     (path stílus: MinIO)
mc_lookup() {
  case "${OFFSITE_LOOKUP:-auto}" in
    dns|virtual|off) echo "off" ;;
    path|on)         echo "on" ;;
    *)               echo "auto" ;;
  esac
}

# Egy mentés a saját lemezén, amit véd, NEM mentés: ha az instance elvész, vele
# vész a `backups` volume is. Ez tolja át a másolatot egy másik hibatartományba.
# Sikertelensége NEM némítható: a helyi mentés ilyenkor is megvan, de a futás
# nem nulla kóddal zárul, hogy a monitorozás lássa.
sync_offsite() {
  DEST="$1"
  if [ -z "${OFFSITE_ENDPOINT:-}" ]; then
    echo "[backup] FIGYELEM: OFFSITE_ENDPOINT nincs beállítva — a mentés CSAK a helyi lemezen van."
    echo "[backup] FIGYELEM: ez nem tekinthető katasztrófa-védelemnek (lásd .env.prod.example)."
    return 0
  fi
  for v in OFFSITE_BUCKET OFFSITE_KEY OFFSITE_SECRET; do
    eval "val=\${$v:-}"
    [ -n "${val}" ] || { echo "[backup] HIBA: ${v} hiányzik, az off-box szinkron kimarad"; return 1; }
  done

  echo "[backup] off-box szinkron -> ${OFFSITE_ENDPOINT}/${OFFSITE_BUCKET}/${OFFSITE_PREFIX}"
  if ! mc alias set offsite "${OFFSITE_ENDPOINT}" "${OFFSITE_KEY}" "${OFFSITE_SECRET}" \
       --api S3v4 --path "$(mc_lookup)" >/dev/null 2>&1; then
    echo "[backup] HIBA: nem sikerült az off-box alias (endpoint/kulcsok/OFFSITE_LOOKUP?)"; return 1
  fi
  # A bucketet a Lightsail konzolon hozzuk létre; a hozzáférési kulcsnak jellemzően
  # NINCS joga bucketet létrehozni, ezért a hibát itt szándékosan elnyeljük — a
  # következő mirror úgyis eldönti, hogy elérhető-e.
  mc mb -p "offsite/${OFFSITE_BUCKET}" >/dev/null 2>&1 || true
  if ! mc mirror --overwrite "${DEST}" "offsite/${OFFSITE_BUCKET}/${OFFSITE_PREFIX}/$(basename "${DEST}")"; then
    echo "[backup] HIBA: az off-box mirror sikertelen"; return 1
  fi

  # Visszaolvasás: a feltöltött DB-archívum mérete egyezzen a helyivel. Enélkül
  # csak azt tudnánk, hogy a parancs lefutott, nem azt, hogy meg is érkezett.
  LOCAL_SIZE="$(wc -c < "${DEST}/db.sql.gz" | tr -d ' ')"
  REMOTE_SIZE="$(mc stat --json "offsite/${OFFSITE_BUCKET}/${OFFSITE_PREFIX}/$(basename "${DEST}")/db.sql.gz" 2>/dev/null \
    | sed -n 's/.*"size":\([0-9]*\).*/\1/p' | head -1)"
  if [ -z "${REMOTE_SIZE}" ] || [ "${LOCAL_SIZE}" != "${REMOTE_SIZE}" ]; then
    echo "[backup] HIBA: off-box ellenőrzés bukott (helyi=${LOCAL_SIZE}B, távoli=${REMOTE_SIZE:-hiányzik})"; return 1
  fi
  echo "[backup] off-box ellenőrzés rendben (${REMOTE_SIZE} B)"

  echo "[backup] off-box retenció: ${OFFSITE_KEEP_DAYS} nap"
  mc rm --recursive --force --older-than "${OFFSITE_KEEP_DAYS}d" \
    "offsite/${OFFSITE_BUCKET}/${OFFSITE_PREFIX}/" >/dev/null 2>&1 \
    || echo "[backup] FIGYELEM: az off-box retenció nem futott le"
  return 0
}

# A DATABASE_URL Doctrine-DSN: a `serverVersion` és `charset` paramétereket a
# libpq NEM ismeri, a pg_dump hibával kilép ("invalid URI query parameter").
# Ezért a dump előtt kiszedjük őket, de MINDEN mást (pl. sslmode) meghagyunk.
pg_dsn() {
  printf '%s' "$1" | sed -E 's/([?&])(serverVersion|charset)=[^&]*/\1/g; s/&&+/\&/g; s/[?&]+$//; s/\?&/?/'
}

run_backup() {
  TS="$(date +%Y%m%d-%H%M%S)"
  DEST="${BACKUP_DIR}/${TS}"
  mkdir -p "${DEST}" || { echo "[backup] HIBA: nem hozható létre ${DEST}"; return 1; }

  # FIGYELEM, ez korábban CSENDBEN bukott: a `pg_dump … | gzip > f` csővezeték
  # kilépési kódja a gzip-é, ami akkor is 0, ha a pg_dump elhasalt. Az eredmény
  # egy 20 bájtos, ÜRES de érvényes gzip — amit a `gzip -t` boldogan átenged.
  # Ezért: külön lépés, saját kilépési kód, és tartalmi ellenőrzés.
  echo "[backup] pg_dump -> ${DEST}/db.sql.gz"
  if ! pg_dump "$(pg_dsn "${DATABASE_URL}")" > "${DEST}/db.sql"; then
    echo "[backup] HIBA: pg_dump sikertelen"; rm -f "${DEST}/db.sql"; return 1
  fi
  # Egy valódi dump tartalmazza a záró sort; enélkül csonka vagy üres.
  if ! grep -q "PostgreSQL database dump complete" "${DEST}/db.sql"; then
    echo "[backup] HIBA: a dump csonka (hiányzik a záró jelölő)"; rm -f "${DEST}/db.sql"; return 1
  fi
  if ! grep -qE "^(CREATE TABLE|COPY )" "${DEST}/db.sql"; then
    echo "[backup] HIBA: a dump nem tartalmaz sémát/adatot"; rm -f "${DEST}/db.sql"; return 1
  fi
  if ! gzip -f "${DEST}/db.sql"; then
    echo "[backup] HIBA: a dump tömörítése sikertelen"; return 1
  fi
  if ! gzip -t "${DEST}/db.sql.gz"; then
    echo "[backup] HIBA: a DB-archívum sérült"; return 1
  fi
  echo "[backup] DB-archívum: $(wc -c < "${DEST}/db.sql.gz" | tr -d ' ') B"

  echo "[backup] dokumentumok mentése (mc mirror ${S3_BUCKET})"
  if ! mc alias set bkp "${S3_ENDPOINT}" "${S3_KEY}" "${S3_SECRET}" >/dev/null 2>&1; then
    echo "[backup] HIBA: nem sikerült a MinIO alias"; return 1
  fi
  # ÜRES bucket esetén az `mc mirror` egyáltalán nem hozza létre a célkönyvtárat,
  # amitől a `tar` elhasalna — és ezzel az EGÉSZ mentés elbukna, a már elkészült
  # adatbázis-dumppal együtt. Egy dokumentum nélküli pilot (vagy egy kiürített
  # bucket) így minden éjjel sikertelen mentést produkálna. Előre létrehozzuk:
  # üres bucketből üres, de érvényes archívum lesz.
  mkdir -p "${DEST}/documents"
  if ! mc mirror --overwrite "bkp/${S3_BUCKET}" "${DEST}/documents"; then
    echo "[backup] HIBA: mc mirror sikertelen"; return 1
  fi
  DOC_COUNT="$(find "${DEST}/documents" -type f | wc -l | tr -d ' ')"
  echo "[backup] mentett dokumentumok: ${DOC_COUNT}"
  if [ "${DOC_COUNT}" = "0" ]; then
    echo "[backup] FIGYELEM: a dokumentum-bucket ÜRES (${S3_BUCKET}). Ha ez váratlan, ellenőrizze a storage-t."
  fi
  if ! tar -czf "${DEST}/documents.tar.gz" -C "${DEST}" documents; then
    echo "[backup] HIBA: dokumentum-archívum sikertelen"; return 1
  fi
  rm -rf "${DEST}/documents"
  gzip -t "${DEST}/documents.tar.gz" || { echo "[backup] HIBA: dokumentum-archívum sérült"; return 1; }

  # Off-box másolat MÉG a retenció előtt: ha ez elbukik, a helyi példány megvan,
  # de a futás nem nulla kóddal zárul, hogy a riasztás elsüljön.
  OFFSITE_RC=0
  sync_offsite "${DEST}" || OFFSITE_RC=1

  echo "[backup] retenció: ${KEEP_DAYS} nap"
  find "${BACKUP_DIR}" -mindepth 1 -maxdepth 1 -type d -mtime "+${KEEP_DAYS}" -exec rm -rf {} +

  echo "[backup] kész: ${DEST}"
  ls -lh "${DEST}"
  return "${OFFSITE_RC}"
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
