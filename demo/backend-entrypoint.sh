#!/bin/sh
# Așteaptă baza de date, aplică migrațiile, populează datele demo (idempotent),
# apoi pornește serverul HTTP built-in al aplicației pe :8080.
set -e

echo "→ Aștept baza de date (db:5432)..."
until php -r '$f=@fsockopen("db",5432); exit($f?0:1);' 2>/dev/null; do
    sleep 2
done

echo "→ Rulez migrațiile Doctrine..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

echo "→ Populez datele demo (idempotent)..."
php bin/console app:demo:seed || true

echo "→ Pornesc backend-ul pe :8080"
exec php -S 0.0.0.0:8080 -t public public/index.php
