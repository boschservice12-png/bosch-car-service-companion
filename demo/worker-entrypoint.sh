#!/bin/sh
# Messenger worker for the demo: consumes the "async" transport (document
# scanning, notifications). Runs in a container separate from the backend.
# Fail-fast: any start-up error stops the process and compose's restart policy
# brings it back — it never "dies quietly".
set -e

echo "→ [worker] Waiting for the database (db:5432)..."
until php -r '$f=@fsockopen("db",5432); exit($f?0:1);' 2>/dev/null; do
    sleep 2
done

# In the demo, migrations and the creation of the messages table (auto_setup)
# are done by the backend at start-up. We wait for the backend to be listening
# before consuming, so we do not race the doctrine transport's auto_setup.
# (Production solves this properly with the one-shot `migrate` service; see
# infrastructure/docker/migrate-entrypoint.sh.)
echo "→ [worker] Waiting for the backend (backend:8080)..."
until php -r '$f=@fsockopen("backend",8080); exit($f?0:1);' 2>/dev/null; do
    sleep 2
done

echo "→ [worker] Starting the Messenger consumer (transport: async)"
# --time-limit: the process restarts periodically with fresh memory; compose
# brings it back. --memory-limit: controlled stop at the threshold. -vv: every
# processed or failed message appears in the log. A message that fails
# max_retries times ends up in the "failed" transport (see messenger.yaml),
# inspectable with `php bin/console messenger:failed:show`.
exec php bin/console messenger:consume async \
    --time-limit=3600 \
    --memory-limit=256M \
    --no-interaction \
    -vv
