#!/bin/sh
# Entrypoint de PRODUCȚIE pentru backend (php-fpm).
# Așteaptă baza, încălzește cache-ul, apoi pornește php-fpm.
#
# NU rulează migrații: proprietarul lor e serviciul one-shot `migrate`, de care
# atât `backend`, cât și `worker` depind cu `service_completed_successfully`.
# Vezi infrastructure/docker/migrate-entrypoint.sh.
set -e

echo "[backend] aștept baza de date…"
until php bin/console dbal:run-sql "SELECT 1" >/dev/null 2>&1; do
  sleep 2
done

echo "[backend] încălzesc cache-ul de producție…"
php bin/console cache:clear --no-interaction
php bin/console cache:warmup --no-interaction

echo "[backend] pornesc php-fpm."
exec php-fpm
