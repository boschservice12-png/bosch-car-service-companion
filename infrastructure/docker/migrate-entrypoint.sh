#!/bin/sh
# Entrypoint pentru serviciul one-shot `migrate` (PRODUCȚIE).
#
# Migrațiile au un SINGUR proprietar: acest container. Rulează o dată, iese cu 0,
# iar `backend` și `worker` pornesc abia după terminarea lui cu succes
# (depends_on: condition: service_completed_successfully).
#
# De ce separat de backend: până acum migrațiile rulau în entrypoint-ul
# backend-ului, iar workerul aștepta doar `service_started` — care se declanșează
# în momentul pornirii containerului, NU după terminarea migrațiilor. Cu
# `auto_setup=1` workerul putea crea `messenger_messages` înaintea migrației
# Version20260715234015, care apoi pica pe tabel duplicat. Cursă nedeterministă,
# invizibilă în CI. Acum bariera e explicită.
set -e

echo "[migrate] aștept baza de date…"
until php bin/console dbal:run-sql "SELECT 1" >/dev/null 2>&1; do
  sleep 2
done

echo "[migrate] rulez migrațiile (idempotent)…"
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

echo "[migrate] migrații aplicate — schema e la zi."
