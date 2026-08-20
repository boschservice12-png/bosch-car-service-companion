#!/usr/bin/env bash
# Regression suite — one reproducible command that runs the whole battery of
# checks. Exits non-zero on the first failure.
#
#   ./scripts/regression.sh
#
# Covers: backend tests (PHPUnit), container lint (prod + test), YAML lint,
# typecheck + lint + build for both frontends, and validation of all three
# docker compose files. Playwright e2e tests are NOT included here — they need
# the stack running; see e2e/README.md for running them locally.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT}"

step() { echo; echo "==== $* ===="; }

step "Backend — PHPUnit (test schema recreated)"
(
  cd backend
  rm -f var/test.db
  php bin/console doctrine:schema:create --env=test -q
  APP_ENV=test php vendor/bin/phpunit
)

step "Backend — container lint (prod + test) + YAML"
(
  cd backend
  php bin/console lint:container --env=prod
  php bin/console lint:container --env=test
  php bin/console lint:yaml config --parse-tags
)

for app in customer-web service-admin; do
  step "Frontend ${app} — typecheck + lint + build"
  (
    cd "apps/${app}"
    [ -d node_modules ] || npm install --no-audit --no-fund
    npm run typecheck
    npm run lint
  )
  # The build goes through verify-build.sh rather than `npm run build`, because
  # `next build` has two side effects that matter here: it writes into .next
  # (breaking any dev server running against the same tree) and it rewrites
  # tsconfig.json in place. The second one is why this matters even in CI —
  # deploy-remote.sh refuses to run on a dirty working tree, so a build that
  # silently edits a tracked file can block a deploy. `--no-lint` is correct:
  # lint already ran above.
  "${ROOT}/scripts/verify-build.sh" "${app}"
done

step "Docker Compose — configuration validation"
docker compose -f compose.demo.yaml config -q
docker compose -f infrastructure/docker/docker-compose.yml config -q

# compose.prod.yaml — the file that describes PRODUCTION — was validated by
# nothing. The reason: its services use `env_file: [.env.prod]`, and that file is
# gitignored and absent from the repo, so `config` failed. `--env-file` does NOT
# help: it only controls variable substitution, not the per-service `env_file`
# directive. So we need a REAL `.env.prod` on disk, either the existing one or a
# temporary one built from the example.
if [ -f .env.prod ]; then
  # On a server with real configuration: use it, do not touch it.
  docker compose --env-file .env.prod -f compose.prod.yaml config -q \
    || { echo "compose.prod.yaml INVALID" >&2; exit 1; }
else
  # On a development machine or in CI: fabricate a temporary one and delete it.
  # The deletion is in a trap so a fake `.env.prod` cannot survive a mid-script
  # failure — a phantom file there would confuse the next deploy.
  sed -e 's|<[^>]*>|placeholder|g' .env.prod.example > .env.prod
  trap 'rm -f "${ROOT}/.env.prod"' EXIT
  docker compose --env-file .env.prod -f compose.prod.yaml config -q \
    || { echo "compose.prod.yaml INVALID" >&2; exit 1; }
  rm -f .env.prod
  trap - EXIT
fi
echo "compose.prod.yaml OK"

echo
echo "==== Regression suite: ALL checks passed ===="
