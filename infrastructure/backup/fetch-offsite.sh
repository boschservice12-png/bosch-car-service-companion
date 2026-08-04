#!/usr/bin/env bash
# Aducerea unui backup DIN depozitul off-box (Lightsail object storage / orice
# S3-compatibil) pe discul local, ca să poată fi dat mai departe la `restore.sh`.
#
# Ăsta e scenariul pentru care există copia off-box: instanța nu mai există, deci
# nici volumul `backups`. Fără scriptul ăsta, backupurile de la distanță ar fi
# recuperabile doar manual, exact în momentul în care nimeni nu vrea să improvizeze.
#
# Utilizare:
#   ./fetch-offsite.sh --list                 # ce backupuri există la distanță
#   ./fetch-offsite.sh --latest               # aduce cel mai recent
#   ./fetch-offsite.sh 20260804-031500        # aduce unul anume
#
# Variabile (aceleași ca la backup):
#   OFFSITE_ENDPOINT / OFFSITE_BUCKET / OFFSITE_KEY / OFFSITE_SECRET
#   OFFSITE_PREFIX  (implicit bcss)
#   OFFSITE_LOOKUP  (implicit auto; dns|path dacă furnizorul cere)
#   FETCH_DIR       (implicit /backups/restaurate) — unde se descarcă
set -euo pipefail

PREFIX="${OFFSITE_PREFIX:-bcss}"
FETCH_DIR="${FETCH_DIR:-/backups/restaurate}"

for v in OFFSITE_ENDPOINT OFFSITE_BUCKET OFFSITE_KEY OFFSITE_SECRET; do
  eval "val=\${$v:-}"
  [ -n "${val}" ] || { echo "[fetch] EROARE: ${v} nesetat." >&2; exit 2; }
done

# `mc --path` acceptă doar auto|on|off; acceptăm și numele descriptive, la fel
# ca backup-cron.sh (dns/virtual = AWS-Lightsail, path = MinIO).
case "${OFFSITE_LOOKUP:-auto}" in
  dns|virtual|off) LOOKUP=off ;;
  path|on)         LOOKUP=on ;;
  *)               LOOKUP=auto ;;
esac

mc alias set offsite "${OFFSITE_ENDPOINT}" "${OFFSITE_KEY}" "${OFFSITE_SECRET}" \
  --api S3v4 --path "${LOOKUP}" >/dev/null

BASE="offsite/${OFFSITE_BUCKET}/${PREFIX}"

list_backups() {
  # Numele directoarelor sunt timestamp-uri (YYYYmmdd-HHMMSS), deci sortarea
  # lexicografică e și cronologică.
  mc ls "${BASE}/" 2>/dev/null | awk '{print $NF}' | tr -d '/' | grep -E '^[0-9]{8}-[0-9]{6}$' | sort
}

ARG="${1:-}"
case "${ARG}" in
  --list|"")
    echo "[fetch] backupuri disponibile în ${BASE}:"
    list_backups | sed 's/^/  /'
    [ -n "${ARG}" ] || { echo; echo "Alegeți unul: ./fetch-offsite.sh <timestamp>  (sau --latest)"; }
    exit 0
    ;;
  --latest)
    TS="$(list_backups | tail -1)"
    [ -n "${TS}" ] || { echo "[fetch] EROARE: nu există niciun backup la distanță." >&2; exit 1; }
    ;;
  *)
    TS="${ARG}"
    ;;
esac

DEST="${FETCH_DIR}/${TS}"
echo "[fetch] aduc ${BASE}/${TS} -> ${DEST}"
mkdir -p "${DEST}"
mc mirror --overwrite "${BASE}/${TS}" "${DEST}"

# Verificăm ce am adus ÎNAINTE de a-l da la restore: o arhivă coruptă în tranzit
# trebuie prinsă aici, nu în mijlocul restaurării.
echo "[fetch] verific arhivele descărcate…"
[ -f "${DEST}/db.sql.gz" ] || { echo "[fetch] EROARE: lipsește db.sql.gz" >&2; exit 1; }
gzip -t "${DEST}/db.sql.gz"
if ! gunzip -c "${DEST}/db.sql.gz" | grep -q "PostgreSQL database dump complete"; then
  echo "[fetch] EROARE: db.sql.gz nu e un dump complet (gol sau trunchiat)." >&2
  exit 1
fi
for a in "${DEST}/documents.tar.gz" "${DEST}/storage.tar.gz"; do
  [ -f "${a}" ] && gzip -t "${a}"
done

echo "[fetch] gata: ${DEST}"
ls -lh "${DEST}"
echo
echo "Restaurare:"
echo "  DATABASE_URL_RESTORE=… restore.sh ${DEST}"
