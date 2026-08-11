#!/usr/bin/env bash
# Pasul de pe SERVER al deploy-ului. Copiat cu `scp` de workflow și rulat CA
# FIȘIER:
#   ssh … "IMAGE_TAG=<sha> bash /tmp/bcss-deploy.sh"
#
# Trăiește în repo (nu inline în YAML) ca să poată fi citit, revizuit și rulat
# manual cu exact aceiași pași:
#   IMAGE_TAG=<sha> bash scripts/deploy-remote.sh
#
# NU se transmite prin stdin (`ssh … 'bash -s' < script`). Așa a fost la prima
# versiune și a produs cel mai urât eșec posibil: `docker compose run` de la
# pasul 2 CITEȘTE stdin, adică restul scriptului. Bash a ajuns la EOF și a ieșit
# cu 0 după pasul 2 — deci pașii 3-6 (pull, up, verificări) nu au rulat NICIODATĂ,
# iar workflow-ul a raportat „succes" în timp ce producția rula în continuare
# imaginile vechi. Un deploy care minte e mai rău decât niciun deploy.
set -euo pipefail

APP_DIR="${APP_DIR:-/opt/bcss}"
COMPOSE=(docker compose --env-file .env.prod -f compose.prod.yaml)
: "${IMAGE_TAG:?IMAGE_TAG lipsește (SHA-ul commit-ului din care s-au construit imaginile)}"

cd "${APP_DIR}"

say() { echo; echo "── $* ──"; }

# ---------------------------------------------------------------------------
say "0. verificări preliminare"

# Un working tree murdar înseamnă că cineva a editat direct pe server. `git
# reset --hard` de mai jos ar șterge acele modificări în tăcere. Mai bine oprim
# și lăsăm un om să decidă.
if ! git diff --quiet || ! git diff --cached --quiet; then
  echo "EROARE: working tree modificat în ${APP_DIR}. Deploy oprit." >&2
  git status --short >&2
  echo "Rezolvați (commit / stash / checkout) și reluați." >&2
  exit 1
fi

[ -f .env.prod ] || { echo "EROARE: ${APP_DIR}/.env.prod lipsește." >&2; exit 1; }

# Reținem versiunea curentă ÎNAINTE de orice schimbare — de asta avem nevoie
# dacă trebuie să ne întoarcem.
PREVIOUS_TAG="$(git rev-parse HEAD)"
echo "versiunea curentă:  ${PREVIOUS_TAG}"
echo "versiunea țintă:    ${IMAGE_TAG}"

rollback_hint() {
  cat >&2 <<EOF

────────────────────────────────────────────────────────────────────────
DEPLOY EȘUAT. Revenire la codul anterior:

  cd ${APP_DIR}
  git reset --hard ${PREVIOUS_TAG}
  IMAGE_TAG=${PREVIOUS_TAG} ${COMPOSE[*]} up -d

ATENȚIE: asta întoarce doar CODUL. Migrațiile aplicate de serviciul
\`migrate\` NU se dau înapoi. Dacă a rulat o migrație distructivă, este
nevoie de restaurare din backup — vezi infrastructure/backup/restore.md.
Backupul dinaintea acestui deploy a fost făcut la pasul 2.
────────────────────────────────────────────────────────────────────────
EOF
}
trap rollback_hint ERR

# ---------------------------------------------------------------------------
say "1. aduc codul la ${IMAGE_TAG}"
git fetch --quiet origin
# Exact commit-ul din care s-au construit imaginile — nu „ultimul de pe main".
# Altfel fișierele bind-montate (nginx, entrypoint-uri) ar putea proveni dintr-un
# commit diferit de cel al imaginilor.
git reset --hard --quiet "${IMAGE_TAG}"
git log --oneline -1

# ---------------------------------------------------------------------------
say "2. backup înainte de deploy"
# Serviciul `migrate` aplică automat migrațiile la pornire, deci un deploy poate
# schimba ireversibil schema. Backupul se face ÎNAINTE, nu după.
export IMAGE_TAG
# `-T` (fără TTY) + stdin din /dev/null: apărare în adâncime, ca acest `run` să
# nu poată consuma niciodată intrarea standard a scriptului. Vezi antetul.
"${COMPOSE[@]}" run --rm -T -e BACKUP_ONESHOT=1 backup < /dev/null

# ---------------------------------------------------------------------------
say "3. descarc imaginile"
# Explicit ÎNAINTE de `up`, cu `set -e`: serviciile au și `build:`, iar un `up`
# care nu găsește imaginea ar începe să CONSTRUIASCĂ pe serverul de producție —
# două compilări Next.js care ocupă ambele vCPU-uri ~15 minute în timp ce
# aceeași mașină servește pilotul. Un pull eșuat trebuie să oprească deploy-ul.
"${COMPOSE[@]}" pull

# ---------------------------------------------------------------------------
say "4. pornesc"
"${COMPOSE[@]}" up -d

# ---------------------------------------------------------------------------
say "5. verific că rulează CHIAR imaginile noi"
# Verificarea asta lipsea la prima versiune, iar absența ei a lăsat un deploy
# complet nereușit să treacă drept „succes": pașii de pull/up nu rulaseră deloc,
# containerele vechi mergeau perfect, deci toate verificările de sănătate au
# trecut. „Site-ul e sus" NU înseamnă „s-a livrat versiunea nouă". Comparăm deci
# eticheta imaginii fiecărui container cu SHA-ul pe care îl livrăm.
STALE=""
for svc in backend worker migrate scheduler customer-web service-admin backup; do
  CID="$("${COMPOSE[@]}" ps -aq "${svc}" 2>/dev/null | tail -1)"
  if [ -z "${CID}" ]; then
    STALE="${STALE} ${svc}(lipsește)"
    continue
  fi
  IMG="$(docker inspect --format '{{.Config.Image}}' "${CID}")"
  case "${IMG}" in
    *":${IMAGE_TAG}") echo "  ${svc}: OK (${IMG##*/})" ;;
    *)                echo "  ${svc}: VECHI -> ${IMG}"; STALE="${STALE} ${svc}" ;;
  esac
done
if [ -n "${STALE}" ]; then
  echo "EROARE: containere care NU rulează ${IMAGE_TAG}:${STALE}" >&2
  echo "Deploy-ul nu a fost aplicat, deși pașii anteriori nu au raportat eroare." >&2
  exit 1
fi

say "6. verific migrațiile"
MIGRATE_CID="$("${COMPOSE[@]}" ps -aq migrate | tail -1)"
if [ -z "${MIGRATE_CID}" ]; then
  echo "EROARE: containerul migrate nu există." >&2; exit 1
fi
MIGRATE_RC="$(docker inspect --format '{{.State.ExitCode}}' "${MIGRATE_CID}")"
if [ "${MIGRATE_RC}" != "0" ]; then
  echo "EROARE: migrate a ieșit cu ${MIGRATE_RC}:" >&2
  "${COMPOSE[@]}" logs migrate --tail 40 >&2
  exit 1
fi
echo "migrate OK (exit 0)"

# ---------------------------------------------------------------------------
say "7. verific sănătatea"
# Aceeași definiție a „sănătos" pe care o folosește monitorizarea — o singură
# sursă de adevăr. Fără BACKUP_DIR: acela cere root, iar deploy-ul rulează ca
# utilizator obișnuit; prospețimea backupului o verifică oricum cronul.
BASE_URL="${DEPLOY_BASE_URL:-https://app.bcss.ro}"

# Backendul tocmai a fost recreat: îi lăsăm timp să pornească și nginx-ului să
# re-rezolve upstream-ul (resolver cu TTL de 10s).
for attempt in $(seq 1 24); do
  if curl -fsS --max-time 5 "${BASE_URL}/api/health" >/dev/null 2>&1; then
    echo "liveness OK după ~$((attempt * 5))s"
    break
  fi
  [ "${attempt}" -eq 24 ] && { echo "EROARE: /api/health nu răspunde după 120s" >&2; \
    "${COMPOSE[@]}" logs backend --tail 40 >&2; exit 1; }
  sleep 5
done

BASE_URL="${BASE_URL}" bash infrastructure/monitoring/healthcheck.sh

trap - ERR
say "deploy reușit: ${PREVIOUS_TAG} -> ${IMAGE_TAG}"
