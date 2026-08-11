#!/bin/sh
# Waits for the database, applies migrations, seeds demo data (idempotent),
# then starts the application's built-in HTTP server on :8080.
set -e

echo "→ Waiting for the database (db:5432)..."
until php -r '$f=@fsockopen("db",5432); exit($f?0:1);' 2>/dev/null; do
    sleep 2
done

echo "→ Running Doctrine migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

echo "→ Seeding demo data (idempotent)..."
# Fail-fast (P0-08): a failed seed STOPS the container — we do not start
# "green" with missing data. Only DEMO_SEED_REQUIRED=false makes it optional.
if [ "${DEMO_SEED_REQUIRED:-true}" = "true" ]; then
    php bin/console app:demo:seed
else
    php bin/console app:demo:seed || echo "⚠ Seed failed (ignored: DEMO_SEED_REQUIRED=false)"
fi

echo "→ Starting the backend on :8080"
exec php -S 0.0.0.0:8080 -t public public/index.php
