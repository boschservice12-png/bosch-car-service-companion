#!/bin/sh
# Planificator de sarcini periodice ale APLICAȚIEI (serviciul `scheduler` din
# compose.prod.yaml). Rulează pe imaginea backend-ului, pentru că are nevoie de
# `bin/console`, nu doar de clientul de PostgreSQL ca serviciul `backup`.
#
# Deocamdată o singură sarcină: `app:gdpr:purge`. Sistemul ține evidențe de
# vehicule ale unor persoane fizice, iar politica de retenție NU se aplică
# singură — comanda există din start, dar nimic nu o rula. Vezi
# docs/security/politica-retentie.md.
#
# Variabile:
#   GDPR_PURGE_HOUR    (implicit 4) — ora rulării zilnice (UTC). Deliberat DUPĂ
#                      backup (ora 3): întâi salvăm, apoi ștergem ireversibil.
#   GDPR_PURGE_ENABLED (implicit 1) — 0 dezactivează complet purjarea
#
# Fără `set -e`: o rulare eșuată se loghează, dar planificatorul trăiește mai
# departe și reîncearcă a doua zi.

HOUR="${GDPR_PURGE_HOUR:-4}"

if [ "${GDPR_PURGE_ENABLED:-1}" != "1" ]; then
  echo "[scheduler] GDPR_PURGE_ENABLED=0 — purjarea e DEZACTIVATĂ."
  echo "[scheduler] Politica de retenție NU se aplică. Intenționat?"
fi

run_purge() {
  if [ "${GDPR_PURGE_ENABLED:-1}" != "1" ]; then
    return 0
  fi
  echo "[scheduler] $(date -u +%FT%TZ) rulez app:gdpr:purge…"
  if php bin/console app:gdpr:purge --no-interaction; then
    echo "[scheduler] app:gdpr:purge a reușit."
    return 0
  fi
  echo "[scheduler] EROARE: app:gdpr:purge a eșuat (reîncerc mâine)."
  return 1
}

# Rulare unică, pentru declanșare manuală:
#   docker compose ... run --rm -e SCHEDULER_ONESHOT=1 scheduler
if [ "${SCHEDULER_ONESHOT:-0}" = "1" ]; then
  run_purge
  exit $?
fi

echo "[scheduler] pornit; app:gdpr:purge zilnic la ${HOUR}:00 UTC."
while true; do
  NOW="$(date +%s)"
  TARGET="$(date -d "today ${HOUR}:00" +%s 2>/dev/null || echo "")"
  if [ -z "${TARGET}" ]; then
    sleep 86400
  else
    [ "${TARGET}" -le "${NOW}" ] && TARGET=$((TARGET + 86400))
    SLEEP=$((TARGET - NOW))
    echo "[scheduler] următoarea rulare peste ${SLEEP} s."
    sleep "${SLEEP}"
  fi
  run_purge || true
done
