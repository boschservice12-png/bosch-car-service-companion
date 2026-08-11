#!/usr/bin/env bash
# Wrapper de cron pentru healthcheck.sh, cu „dead-man's switch".
#
# De ce un wrapper și nu direct healthcheck.sh în cron: un monitor care rulează
# PE mașina monitorizată nu poate raporta niciodată că mașina a murit. Aici
# inversăm sensul semnalului — la fiecare rulare reușită trimitem un ping către
# un serviciu extern (healthchecks.io). Dacă pingul ÎNCETEAZĂ, indiferent de
# motiv (verificare picată, cron oprit, disc plin, instanță dispărută), serviciul
# extern alertează. Tăcerea devine ea însăși alarma.
#
# Instalare: vezi monitoring.md.
#
# Variabile (din /etc/bcss-monitoring.env):
#   BASE_URL          — rădăcina publică (ex. https://app.bcss.ro)
#   HC_PING_URL       — URL-ul de ping healthchecks.io pentru ACEASTĂ verificare
#   BACKUP_DIR        — opțional; verifică vârsta ultimului backup local
#   BACKUP_MAX_AGE_H  — implicit 26
#   LOG_FILE          — implicit /var/log/bcss-healthcheck.log
set -uo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOG_FILE="${LOG_FILE:-/var/log/bcss-healthcheck.log}"
TS="$(date -u +%FT%TZ)"

ping_hc() { # cale (gol = succes), corp
  [ -n "${HC_PING_URL:-}" ] || return 0
  curl -fsS -m 10 --retry 3 --data-raw "${2:-}" "${HC_PING_URL}${1:-}" >/dev/null 2>&1 || true
}

# Semnalăm startul: healthchecks.io poate astfel alerta și dacă rularea se
# BLOCHEAZĂ (începe, nu se mai termină) — nu doar dacă lipsește complet.
ping_hc "/start" ""

OUTPUT="$(BASE_URL="${BASE_URL:-}" \
          BACKUP_DIR="${BACKUP_DIR:-}" \
          BACKUP_MAX_AGE_H="${BACKUP_MAX_AGE_H:-26}" \
          bash "${HERE}/healthcheck.sh" 2>&1)"
RC=$?

printf '%s [rc=%s]\n%s\n' "${TS}" "${RC}" "${OUTPUT}" >> "${LOG_FILE}" 2>/dev/null

# Și pe stdout: în cron oricum se redirectează spre /dev/null, dar la o rulare
# manuală (exact cea din instrucțiunile de instalare) un script complet MUT nu
# lasă omul să distingă „a trecut" de „nu a rulat deloc".
printf '%s\n' "${OUTPUT}"

if [ "${RC}" -eq 0 ]; then
  ping_hc "" "${OUTPUT}"
else
  # Trimitem output-ul integral ca și corp — alerta conține motivul, nu doar
  # „ceva a picat", ca să nu fie nevoie de SSH pentru a afla ce.
  ping_hc "/fail" "${OUTPUT}"
fi

exit "${RC}"
