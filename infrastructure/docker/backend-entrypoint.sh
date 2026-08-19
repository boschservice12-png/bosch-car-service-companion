#!/bin/sh
# PRODUCTION entrypoint for the backend (php-fpm).
# Waits for the database, warms the cache, then starts php-fpm.
#
# It does NOT run migrations: those are owned by the one-shot `migrate` service,
# which both `backend` and `worker` depend on via
# `service_completed_successfully`. See infrastructure/docker/migrate-entrypoint.sh.
set -e

echo "[backend] waiting for the database…"
until php bin/console dbal:run-sql "SELECT 1" >/dev/null 2>&1; do
  sleep 2
done

echo "[backend] warming the production cache…"
php bin/console cache:clear --no-interaction
php bin/console cache:warmup --no-interaction

echo "[backend] starting php-fpm."
exec php-fpm
