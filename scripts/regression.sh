#!/usr/bin/env bash
# Suită de regresie pentru pilot-readiness — o singură comandă reproductibilă
# care rulează întreaga baterie de verificări. Iese nenul la primul eșec.
#
#   ./scripts/regression.sh
#
# Acoperă: teste backend (PHPUnit), lint container (prod + test), lint YAML,
# typecheck + lint + build pentru ambele frontend-uri, și validarea celor două
# fișiere docker compose. Testele Playwright e2e NU sunt incluse aici — ele cer
# stiva pornită; vezi e2e/README.md pentru rularea lor locală.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT}"

step() { echo; echo "==== $* ===="; }

step "Backend — PHPUnit (schema de test recreată)"
(
  cd backend
  rm -f var/test.db
  php bin/console doctrine:schema:create --env=test -q
  APP_ENV=test php vendor/bin/phpunit
)

step "Backend — lint container (prod + test) + YAML"
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
    npm run build
  )
done

step "Docker Compose — validare configurație"
docker compose -f compose.demo.yaml config -q
docker compose -f infrastructure/docker/docker-compose.yml config -q

# compose.prod.yaml — fișierul care descrie PRODUCȚIA — nu era validat de nimic.
# Motivul: serviciile au `env_file: [.env.prod]`, iar acel fișier e gitignorat și
# lipsește din repo, deci `config` pica. `--env-file` NU ajută: el controlează
# doar substituția variabilelor, nu directiva `env_file` per serviciu. Deci avem
# nevoie de un `.env.prod` REAL pe disc, fie cel existent, fie unul temporar
# construit din exemplu.
if [ -f .env.prod ]; then
  # Pe un server cu configurație reală: o folosim, nu o atingem.
  docker compose --env-file .env.prod -f compose.prod.yaml config -q \
    || { echo "compose.prod.yaml INVALID" >&2; exit 1; }
else
  # Pe o mașină de dezvoltare / în CI: fabricăm unul temporar și îl ștergem.
  # Ștergerea e în trap, ca să nu rămână un `.env.prod` fals dacă scriptul pică
  # la mijloc — un fișier fantomă acolo ar deruta următorul deploy.
  sed -e 's|<[^>]*>|placeholder|g' .env.prod.example > .env.prod
  trap 'rm -f "${ROOT}/.env.prod"' EXIT
  docker compose --env-file .env.prod -f compose.prod.yaml config -q \
    || { echo "compose.prod.yaml INVALID" >&2; exit 1; }
  rm -f .env.prod
  trap - EXIT
fi
echo "compose.prod.yaml OK"

echo
echo "==== Suită de regresie: TOATE verificările au trecut ===="
