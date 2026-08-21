#!/usr/bin/env bash
# Production-build one or both frontends WITHOUT disturbing a running dev server.
#
#   ./scripts/verify-build.sh                 # both apps
#   ./scripts/verify-build.sh customer-web    # one app
#
# Two hazards this exists to avoid, both of which have already bitten:
#
#  1. `next build` writing into the .next a dev server is serving from kills the
#     running app with "__webpack_modules__[moduleId] is not a function" until
#     the server is restarted and .next is deleted. So we build into .next-verify
#     via BUILD_DIST_DIR (see each app's next.config.mjs). Note that `--distDir`
#     is NOT a CLI flag on Next 15.5 — it exits with `unknown option`, having
#     built nothing, which looks exactly like success to a script checking $?.
#
#  2. `next build` rewrites tsconfig.json in place: it reformats the JSON and
#     appends the active distDir's types glob to "include". With a temporary
#     dist dir that means ".next-verify/types/**/*.ts" gets committed. We snapshot
#     tsconfig.json and restore it afterwards.
#
#     The snapshot also has to be edited for the duration, not merely kept: while
#     "include" still points at .next/types, the type-check reads the route types
#     the *dev server* is generating into .next, concurrently with the build
#     writing .next-verify. That fails intermittently — "Failed to compile" with
#     no attributable error — so the glob is repointed at the verify dist dir
#     while the build runs.
set -euo pipefail

cd "$(dirname "$0")/.."
APPS=("${@:-customer-web service-admin}")
read -r -a APPS <<< "${APPS[*]}"

for app in "${APPS[@]}"; do
  dir="apps/${app}"
  [ -d "$dir" ] || { echo "no such app: ${app}" >&2; exit 2; }

  echo "==> ${app}"
  snapshot="$(mktemp)"
  cp "${dir}/tsconfig.json" "$snapshot"
  # Restore even if the build fails or the run is interrupted.
  trap 'cp "$snapshot" "'"${dir}"'/tsconfig.json"; rm -f "$snapshot"' EXIT

  # Read this build's route types from the dist dir this build is writing.
  sed -i.bak 's|\.next/types/\*\*/\*\.ts|.next-verify/types/**/*.ts|' "${dir}/tsconfig.json"
  rm -f "${dir}/tsconfig.json.bak"

  (
    cd "$dir"
    BUILD_DIST_DIR=.next-verify npx next build --no-lint
    rm -rf .next-verify
  )

  cp "$snapshot" "${dir}/tsconfig.json"
  rm -f "$snapshot"
  trap - EXIT
done

echo
echo "Builds clean. tsconfig.json restored; dev servers untouched."
