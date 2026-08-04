#!/bin/sh
# Worker Messenger pentru demo: consumă transportul „async" (scanare documente,
# notificări). Rulează în container separat de backend. Fail-fast: orice eroare
# de pornire oprește procesul, iar politica de restart din compose îl repornește
# — nu „moare în tăcere".
set -e

echo "→ [worker] Aștept baza de date (db:5432)..."
until php -r '$f=@fsockopen("db",5432); exit($f?0:1);' 2>/dev/null; do
    sleep 2
done

# Migrațiile și crearea tabelei de mesaje (auto_setup) le face backend-ul la
# pornire. Așteptăm ca backend-ul să fie sus înainte de a consuma, ca să nu
# concurăm pe auto_setup-ul transportului doctrine.
echo "→ [worker] Aștept backend-ul (backend:8080)..."
until php -r '$f=@fsockopen("backend",8080); exit($f?0:1);' 2>/dev/null; do
    sleep 2
done

echo "→ [worker] Pornesc consumatorul Messenger (transport: async)"
# --time-limit: procesul se reia periodic (memorie proaspătă); restart-ul din
# compose îl repornește. --memory-limit: oprire controlată la prag. -vv: fiecare
# mesaj procesat/eșuat apare în log. Un mesaj care eșuează de max_retries ori
# ajunge în transportul „failed" (vezi messenger.yaml), inspectabil cu
# `php bin/console messenger:failed:show`.
exec php bin/console messenger:consume async \
    --time-limit=3600 \
    --memory-limit=256M \
    --no-interaction \
    -vv
