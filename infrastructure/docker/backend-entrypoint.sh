#!/bin/sh
# Entrypoint de PRODUCȚIE pentru backend (php-fpm).
# Așteaptă baza, rulează migrațiile CONTROLAT (un singur container o face — cel
# de backend, NU workerul), încălzește cache-ul, apoi pornește php-fpm.
set -e

echo "[backend] aștept baza de date…"
until php bin/console dbal:run-sql "SELECT 1" >/dev/null 2>&1; do
  sleep 2
done

echo "[backend] rulez migrațiile (idempotent)…"
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

echo "[backend] încălzesc cache-ul de producție…"
php bin/console cache:clear --no-interaction
php bin/console cache:warmup --no-interaction

echo "[backend] pornesc php-fpm."
exec php-fpm
