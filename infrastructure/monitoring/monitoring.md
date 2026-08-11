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

## Ce rulează deja în stivă (NU puneți în cron)

Backupul și purjarea GDPR sunt servicii în `compose.prod.yaml`, nu joburi de
gazdă. Nu le dublați în crontab:

| Job | Serviciu | Program |
|---|---|---|
| Backup DB + documente, local + off-box | `backup` | zilnic 03:00 UTC (`BACKUP_HOUR`) |
| `app:gdpr:purge` | `scheduler` | zilnic 04:00 UTC (`GDPR_PURGE_HOUR`) |

## Monitorizare: „dead-man's switch"

Problema de fond: **un monitor care rulează pe mașina monitorizată nu poate
raporta niciodată că mașina a murit.** Dacă instanța se oprește, un cron local
care „alertează la eșec" nu alertează — pur și simplu tace, la fel ca atunci
când totul e în regulă.

De aceea inversăm semnalul. La fiecare rulare REUȘITĂ trimitem un ping către un
serviciu extern (healthchecks.io). Serviciul alertează când pingul ÎNCETEAZĂ —
verificare picată, cron oprit, disc plin sau instanță dispărută. Tăcerea devine
alarma.

Trei verificări, cu programe și costuri diferite:

| Script | Frecvență | Ce acoperă |
|---|---|---|
| `cron-healthcheck.sh` | 5 min | liveness, readiness (inclusiv `scanner`/`messenger`), disc, vârsta ȘI dimensiunea backupului LOCAL |
| `check-offsite-freshness.sh` | zilnic 05:00 UTC | există un backup recent ÎN BUCKET |
| `../../scripts/restore-drill.sh` | lunar, ziua 1, 06:00 UTC | backupul off-box chiar se **restaurează** și conține date |

A treia e cea care contează pe termen lung. „Restaurarea a fost testată" e
adevărat exact în ziua în care cineva a testat-o; peste trei luni e din nou o
presupunere. Drill-ul aduce cel mai recent backup DIN bucket, îl restaurează
într-o bază de unică folosință, compară setul de tabele, numărul de migrații și
rândurile față de producție, apoi șterge baza. Producția nu e atinsă, iar baza
temporară se curăță și dacă drill-ul pică.

A doua nu e un lux. `healthcheck.sh` se uită doar la backupurile locale: dacă
cheile Lightsail expiră sau sunt rotite, mentințele locale continuă să reușească,
prospețimea locală rămâne verde, iar copia off-box se oprește în tăcere — exact
scenariul pentru care există. Rulează o dată pe zi pentru că pornește un
container, nu doar un `curl`.

### Instalare (o singură dată, pe server)

1. Creați **trei** verificări în healthchecks.io și copiați URL-urile de ping:

   | Verificare | Period | Grace |
   |---|---|---|
   | BCSS health | 5 minute | 5 minute |
   | BCSS off-box backup | 1 day | 6 hours |
   | BCSS restore drill | 31 days | 2 days |

2. Puneți configurația într-un fișier cu drepturi restrânse. **URL-urile de ping
   sunt secrete** — oricine le are poate falsifica „sunt sănătos":

   ```bash
   sudo tee /etc/bcss-monitoring.env >/dev/null <<'EOF'
   BASE_URL=https://app.bcss.ro
   BACKUP_DIR=/var/lib/docker/volumes/bcsc-prod_backups/_data
   COMPOSE_DIR=/opt/bcss
   HC_PING_URL=https://hc-ping.com/<uuid-health>
   HC_PING_URL_OFFSITE=https://hc-ping.com/<uuid-offsite>
   HC_PING_URL_DRILL=https://hc-ping.com/<uuid-drill>
   EOF
   sudo chmod 600 /etc/bcss-monitoring.env
   ```

3. Crontab-ul lui `root` (are nevoie de root: citește volumul de backup și
   vorbește cu Docker). Ambele scripturi își citesc SINGURE
   `/etc/bcss-monitoring.env`, deci liniile rămân scurte:

   ```cron
   */5 * * * * /opt/bcss/infrastructure/monitoring/cron-healthcheck.sh >/dev/null 2>&1
   0 5 * * * /opt/bcss/infrastructure/monitoring/check-offsite-freshness.sh >/dev/null 2>&1
   0 6 1 * * /opt/bcss/scripts/restore-drill.sh >/dev/null 2>&1
   ```

   O intrare de crontab trebuie să încapă pe o **singură linie fizică**. Varianta
   cu `set -a; . /etc/…; set +a;` inline depășea 130 de caractere și se rupea la
   copiere, iar cron o respingea cu `bad minute`. De aici auto-citirea din
   scripturi. Altă cale de configurare: `BCSS_MONITORING_ENV=/alt/fișier`.

   Ora 05:00 pentru a doua e deliberată: după fereastra de backup (03:00), cu
   marjă dacă rularea durează.

   Verificați că s-au instalat ca TREI intrări, nu șase (o intrare de crontab
   ruptă în două e respinsă cu `bad minute`):

   ```bash
   sudo crontab -l | grep -cE 'monitoring/|restore-drill'   # trebuie să dea 3
   ```

4. Verificați că funcționează **provocând un eșec**, nu doar o reușită:

   ```bash
   # trebuie să apară ca „down" în healthchecks.io în câteva minute
   sudo docker compose --env-file /opt/bcss/.env.prod -f /opt/bcss/compose.prod.yaml stop api
   # …apoi reporniți și confirmați revenirea
   sudo docker compose --env-file /opt/bcss/.env.prod -f /opt/bcss/compose.prod.yaml start api
   ```

   O alertă netestată e o presupunere. Asta e singurul mod de a ști că lanțul
   întreg — cron → script → ping → notificare — chiar ajunge la un om.

Loguri locale: `/var/log/bcss-healthcheck.log`, `/var/log/bcss-offsite-check.log`,
`/var/log/bcss-restore-drill.log`.

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
