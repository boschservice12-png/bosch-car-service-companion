#!/bin/sh
# Entrypoint for the one-shot `migrate` service (PRODUCTION).
#
# Migrations have a SINGLE owner: this container. It runs once, exits 0, and
# `backend` and `worker` only start after it has finished successfully
# (depends_on: condition: service_completed_successfully).
#
# Why separate from the backend: migrations used to run in the backend's
# entrypoint while the worker waited only for `service_started` — which fires
# the moment the container starts, NOT when migrations finish. With
# `auto_setup=1` the worker could create `messenger_messages` before migration
# Version20260715234015, which then failed on a duplicate table. A
# non-deterministic race, invisible in CI. The barrier is now explicit.
set -e

echo "[migrate] waiting for the database…"
until php bin/console dbal:run-sql "SELECT 1" >/dev/null 2>&1; do
  sleep 2
done

echo "[migrate] running migrations (idempotent)…"
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

echo "[migrate] migrations applied — the schema is up to date."
