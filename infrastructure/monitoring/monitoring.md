# Monitorizare și operare (P1-07)

Sistemul e un monolit Symfony + două aplicații Next.js, cu PostgreSQL și
storage local de documente. Monitorizarea minimă viabilă acoperă patru
întrebări: *răspunde?*, *are dependențele?*, *are loc pe disc?*, *există
backup proaspăt?* — toate verificate de `healthcheck.sh`.

## Verificarea de sănătate

```bash
BASE_URL=https://service.example.ro \
BACKUP_DIR=/backups \
infrastructure/monitoring/healthcheck.sh
```

- `GET /api/health` — procesul PHP răspunde (liveness);
- `GET /api/health/ready` — baza de date și storage-ul funcționează (readiness);
- procent de ocupare disc (implicit alertă > 85%);
- vârsta ultimului backup (implicit alertă > 26h).

Exit code `0` = totul în regulă; orice altceva = alertă. Se leagă de orice
uptime-checker (cron + mail, Uptime Kuma, healthchecks.io etc.).

## Cron recomandat (pe gazdă)

```cron
# Backup zilnic la 02:30 (DB + documente, retenție 14 zile)
30 2 * * *  DATABASE_URL=postgresql://… STORAGE_DIR=/app/var/storage BACKUP_DIR=/backups  /opt/bcsc/infrastructure/backup/backup.sh >> /var/log/bcsc-backup.log 2>&1

# Verificare de sănătate la 5 minute; alertează pe exit code nenul
*/5 * * * *  BASE_URL=https://service.example.ro BACKUP_DIR=/backups  /opt/bcsc/infrastructure/monitoring/healthcheck.sh || <comanda-de-alertare>

# Purjare GDPR zilnică la 03:15 (după backup) — vezi docs/security/politica-retentie.md
15 3 * * *  /usr/bin/php /opt/bcsc/backend/bin/console app:gdpr:purge >> /var/log/bcsc-gdpr.log 2>&1
```

## Ce mai merită urmărit (din logurile aplicației)

| Semnal | Unde | De ce |
|---|---|---|
| Valuri de `429` | loguri Nginx/aplicație | abuz pe login/mesaje/upload (P1-04) |
| `403 two_factor_required` repetat | loguri aplicație | cineva forțează un cont de admin |
| `identity.2fa_reset`, `user.import_account_claimed` | tabela `audit_logs` | operațiuni sensibile — de revizuit periodic |
| Erori `5xx` cu `traceId` | application/problem+json | corelarea incidentelor raportate de utilizatori |
| Documente cu `scanStatus = INFECTED` | tabela documentelor | încărcări rău-intenționate |

## Loguri

- Backend: `var/log/` (dev) / stdout container (prod) — se agregă cu
  driverul de logging al Docker (`json-file` cu `max-size`/`max-file` sau
  jurnalul gazdei). Nu e nevoie de logrotate propriu în container.
- Nginx: acces + erori, rotite de imaginea oficială.

## Restaurare

Procedura completă și drill-ul lunar: [`../backup/restore.md`](../backup/restore.md).
Regula de aur: un backup nerestaurat de probă nu se consideră backup.
