#!/usr/bin/env bash
# The SERVER-side step of a deploy. Copied over with `scp` by the workflow and
# run AS A FILE:
#   ssh … "IMAGE_TAG=<sha> bash /tmp/bcss-deploy.sh"
#
# It lives in the repo (rather than inline in YAML) so it can be read, reviewed,
# and run by hand with exactly the same steps:
#   IMAGE_TAG=<sha> bash scripts/deploy-remote.sh
#
# It is NOT piped through stdin (`ssh … 'bash -s' < script`). That was the first
# version and it produced the worst possible failure: `docker compose run` in
# step 2 READS stdin — which was the rest of the script. Bash hit EOF and exited
# 0 after step 2, so steps 3-6 (pull, up, verification) never ran at all, while
# the workflow reported "success" and production kept serving the old images.
# A deploy that lies is worse than no deploy.
set -euo pipefail

APP_DIR="${APP_DIR:-/opt/bcss}"
COMPOSE=(docker compose --env-file .env.prod -f compose.prod.yaml)
# The services we build — the only ones a deploy is allowed to update.
# db/redis/clamav/minio/nginx/caddy are touched only deliberately.
APP_SERVICES=(backend worker migrate scheduler customer-web service-admin backup)
: "${IMAGE_TAG:?IMAGE_TAG is missing (the commit SHA the images were built from)}"

cd "${APP_DIR}"

say() { echo; echo "── $* ──"; }

# ---------------------------------------------------------------------------
say "0. preliminary checks"

# A dirty working tree means somebody edited files directly on the server. The
# `git reset --hard` below would silently discard those changes. Better to stop
# and let a human decide.
if ! git diff --quiet || ! git diff --cached --quiet; then
  echo "ERROR: working tree modified in ${APP_DIR}. Deploy aborted." >&2
  git status --short >&2
  echo "Resolve it (commit / stash / checkout) and retry." >&2
  exit 1
fi

[ -f .env.prod ] || { echo "ERROR: ${APP_DIR}/.env.prod is missing." >&2; exit 1; }

# Remember the current version BEFORE any change — this is what we need if we
# have to go back.
PREVIOUS_TAG="$(git rev-parse HEAD)"
echo "current version: ${PREVIOUS_TAG}"
echo "target version:  ${IMAGE_TAG}"

rollback_hint() {
  cat >&2 <<EOF

────────────────────────────────────────────────────────────────────────
DEPLOY FAILED. To go back to the previous code:

  cd ${APP_DIR}
  git reset --hard ${PREVIOUS_TAG}
  IMAGE_TAG=${PREVIOUS_TAG} ${COMPOSE[*]} up -d

CAUTION: that reverts CODE only. Migrations applied by the \`migrate\`
service are NOT rolled back. If a destructive migration ran, you need a
restore from backup — see infrastructure/backup/restore.md. The backup
taken before this deploy is from step 2.
────────────────────────────────────────────────────────────────────────
EOF
}
trap rollback_hint ERR

# ---------------------------------------------------------------------------
say "1. checking out ${IMAGE_TAG}"
git fetch --quiet origin
# The exact commit the images were built from — not "the latest on main".
# Otherwise bind-mounted files (nginx config, entrypoints) could come from a
# different commit than the images.
git reset --hard --quiet "${IMAGE_TAG}"
git log --oneline -1

# ---------------------------------------------------------------------------
say "2. pre-deploy backup"
# The `migrate` service applies migrations automatically at start-up, so a
# deploy can change the schema irreversibly. The backup happens BEFORE, not
# after.
export IMAGE_TAG
# `-T` (no TTY) plus stdin from /dev/null: defence in depth, so this `run` can
# never consume the script's standard input. See the header.
"${COMPOSE[@]}" run --rm -T -e BACKUP_ONESHOT=1 backup < /dev/null

# ---------------------------------------------------------------------------
say "3. pulling images"
# Explicitly BEFORE `up`, with `set -e`: the services still carry a `build:`
# section, so an `up` that cannot find an image would start COMPILING on the
# production server — two Next.js builds pegging both vCPUs for ~15 minutes
# while the same machine serves the pilot. A failed pull must abort the deploy.
#
# ONLY our own services. An argument-less `pull` fetches everything, including
# postgres:16, redis:7, clamav:stable and minio — floating tags. A new digest
# makes compose RECREATE the container, meaning every deploy would quietly
# upgrade the database and the antivirus. First seen on 2026-08-11: ClamAV was
# recreated and its signature databases take minutes to reload, so the health
# check failed immediately after an otherwise successful deploy. Upgrading
# third-party components must be a separate, deliberate decision.
"${COMPOSE[@]}" pull "${APP_SERVICES[@]}"

# ---------------------------------------------------------------------------
say "4. starting"
"${COMPOSE[@]}" up -d

# ---------------------------------------------------------------------------
say "5. verifying the new images are ACTUALLY running"
# This check was missing in the first version, and its absence let a completely
# failed deploy pass as "success": the pull and up steps had not run at all, the
# old containers were perfectly healthy, so every health check passed. "The site
# is up" does NOT mean "the new version shipped". So compare each container's
# image tag against the SHA we are deploying.
STALE=""
for svc in "${APP_SERVICES[@]}"; do
  CID="$("${COMPOSE[@]}" ps -aq "${svc}" 2>/dev/null | tail -1)"
  if [ -z "${CID}" ]; then
    STALE="${STALE} ${svc}(missing)"
    continue
  fi
  IMG="$(docker inspect --format '{{.Config.Image}}' "${CID}")"
  case "${IMG}" in
    *":${IMAGE_TAG}") echo "  ${svc}: OK (${IMG##*/})" ;;
    *)                echo "  ${svc}: STALE -> ${IMG}"; STALE="${STALE} ${svc}" ;;
  esac
done
if [ -n "${STALE}" ]; then
  echo "ERROR: containers not running ${IMAGE_TAG}:${STALE}" >&2
  echo "The deploy was not applied, even though no earlier step reported an error." >&2
  exit 1
fi

say "6. verifying migrations"
MIGRATE_CID="$("${COMPOSE[@]}" ps -aq migrate | tail -1)"
if [ -z "${MIGRATE_CID}" ]; then
  echo "ERROR: the migrate container does not exist." >&2; exit 1
fi
MIGRATE_RC="$(docker inspect --format '{{.State.ExitCode}}' "${MIGRATE_CID}")"
if [ "${MIGRATE_RC}" != "0" ]; then
  echo "ERROR: migrate exited with ${MIGRATE_RC}:" >&2
  "${COMPOSE[@]}" logs migrate --tail 40 >&2
  exit 1
fi
echo "migrate OK (exit 0)"

# ---------------------------------------------------------------------------
say "7. verifying health"
# The same definition of "healthy" the monitoring uses — a single source of
# truth. No BACKUP_DIR: that needs root and the deploy runs as a normal user;
# backup freshness is checked by cron anyway.
BASE_URL="${DEPLOY_BASE_URL:-https://app.bcss.ro}"

# The backend was just recreated: give it time to start and give nginx time to
# re-resolve the upstream (resolver TTL is 10s).
for attempt in $(seq 1 24); do
  if curl -fsS --max-time 5 "${BASE_URL}/api/health" >/dev/null 2>&1; then
    echo "liveness OK after ~$((attempt * 5))s"
    break
  fi
  [ "${attempt}" -eq 24 ] && { echo "ERROR: /api/health did not respond within 120s" >&2; \
    "${COMPOSE[@]}" logs backend --tail 40 >&2; exit 1; }
  sleep 5
done

# NONCRITICAL_MODE=warn: a deploy fails only on CRITICAL dependencies.
# Non-critical checks can be red temporarily at start-up; the five-minute
# monitoring catches them if they stay that way. See healthcheck.sh.
BASE_URL="${BASE_URL}" NONCRITICAL_MODE=warn bash infrastructure/monitoring/healthcheck.sh

trap - ERR
say "deploy succeeded: ${PREVIOUS_TAG} -> ${IMAGE_TAG}"
