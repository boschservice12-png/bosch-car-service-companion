# Procedură de restaurare (testată periodic)

> Un backup fără restaurare testată nu este un backup. Rulați acest drill lunar
> pe un mediu izolat și consemnați rezultatul.

## Pași

1. Pregătire mediu curat (docker compose separat, bază goală).
2. Restaurare bază de date:
   ```bash
   gunzip -c <backup>/db.sql.gz | psql "$DATABASE_URL_RESTORE"
   ```
3. Restaurare documente (storage local):
   ```bash
   mkdir -p /app/var/storage
   tar -xzf <backup>/storage.tar.gz -C /app/var/storage
   ```
4. Verificări post-restaurare:
   - migrări la zi (`doctrine:migrations:status`);
   - health `GET /api/health/ready` = 200;
   - un client de test își vede vehiculele și documentele;
   - un document se descarcă prin URL temporar.
5. Consemnați: data, durata (RTO), pierderea maximă (RPO), probleme.

## Alerte obligatorii
- backup eșuat (exit code nenul din `backup.sh`);
- niciun backup în ultimele 26h (`healthcheck.sh` cu `BACKUP_DIR`);
- storage indisponibil / disc peste prag (`healthcheck.sh`).

Detalii de monitorizare și cron: [`../monitoring/monitoring.md`](../monitoring/monitoring.md).
