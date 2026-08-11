#!/usr/bin/env bash
# Pasul de pe SERVER al deploy-ului. Rulat de .github/workflows/deploy.yml prin
#   ssh … 'IMAGE_TAG=<sha> bash -s' < scripts/deploy-remote.sh
#
# Trăiește în repo (nu inline în YAML) ca să poată fi citit, revizuit și rulat
# manual cu exact aceiași pași:
#   IMAGE_TAG=<sha> bash scripts/deploy-remote.sh
#
# Se transmite prin stdin, deci versiunea care rulează e cea din commit-ul NOU,
# chiar dacă checkout-ul de pe disc e încă cel vechi.
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
"${COMPOSE[@]}" run --rm -e BACKUP_ONESHOT=1 backup

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
say "5. verific migrațiile"
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
say "6. verific sănătatea"
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
