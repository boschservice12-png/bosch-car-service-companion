# Monitoring

Three scheduled checks, all built on the same idea: **a monitor running on the
monitored machine can never report that the machine died.**

---

## 1. The dead man's switch

A local cron that "alerts on failure" says nothing when the instance is gone —
it is silent, exactly as it is when everything is fine.

So the signal is inverted. Every **successful** run pings an external service
(healthchecks.io). If the pings **stop** — failed check, dead cron, full disk,
vanished instance — the external service alerts. Silence becomes the alarm.

## 2. What runs

| Script | Frequency | Covers |
|---|---|---|
| `infrastructure/monitoring/cron-healthcheck.sh` | every 5 min | liveness, readiness (including the non-critical `scanner` and `messenger` probes), disk, and the age **and size** of the latest local backup |
| `infrastructure/monitoring/check-offsite-freshness.sh` | daily 05:00 UTC | a recent backup actually exists **in the bucket** |
| `scripts/restore-drill.sh` | monthly, 1st 06:00 UTC | the off-box backup genuinely **restores** and contains data |

Each pings a **different** healthchecks.io check.

**The second is not redundant.** `healthcheck.sh` only inspects local backups. If
the Lightsail credentials expire or are rotated, local backups keep succeeding,
local freshness stays green, and the off-box copy stops silently — precisely the
scenario it exists to survive. It runs daily rather than every five minutes
because it starts a container rather than issuing a curl.

**The third is what keeps the claim true over time.** See
[Backup and restore §6](BACKUP_AND_RESTORE.md).

## 3. What `healthcheck.sh` checks

```bash
BASE_URL=https://app.bcss.ro bash /opt/bcss/infrastructure/monitoring/healthcheck.sh
```

| Check | Fails when |
|---|---|
| `liveness GET /api/health` | The PHP process is not answering |
| `readiness GET /api/health/ready` | A **critical** dependency is down (503) |
| `readiness/scanner` | ClamAV is unreachable |
| `readiness/messenger` | The async transport is unreachable |
| `disk` | Usage above `DISK_MAX_PCT` (default 85%) |
| `backup` | No timestamped backup within `BACKUP_MAX_AGE_H` (26h), **or** `db.sql.gz` smaller than `BACKUP_MIN_DUMP_BYTES` (1024) |

Two of those exist because of real incidents.

**The scanner probe.** Readiness returns 200 even with a dead scanner — that is
intentional, since the rest of the API stays servable. But it means a plain
`curl -f` never sees it, and document processing stalls in complete silence. The
check reads `checks.scanner.status` from the JSON.

**The backup size assertion.** The check used to ask "is there a recent
directory?" and never "does it contain anything?". For seven nights `db.sql.gz`
was 20 bytes — an empty but valid gzip — and the check stayed green throughout.
It now also asserts the dump exceeds 1 KB. Real dumps are ~7 KB; empty ones are
20 bytes, so the margin is wide.

The freshness check also filters to timestamp-shaped directory names only.
Without that filter it sorted alphabetically and picked `restored/` — the
fetch working directory — reporting the date of the last manual download as
"the latest backup".

### `NONCRITICAL_MODE`

Default `fail`: any non-critical probe failing fails the check. That is right for
cron.

The deploy gate passes `NONCRITICAL_MODE=warn`, so only **critical** dependencies
can fail a deploy. ClamAV takes minutes to load its signature databases after a
restart, and failing a deploy over that reports failure for a successful
delivery. If a non-critical probe stays red, the five-minute cron catches it.

## 4. Installation

Already done for the current server. This is for rebuilding it.

**1. Create three checks in healthchecks.io:**

| Check | Period | Grace |
|---|---|---|
| BCSS health | 5 minutes | 5 minutes |
| BCSS off-box backup | 1 day | 6 hours |
| BCSS restore drill | 31 days | 2 days |

**2. Configuration file** — the ping URLs are secrets; anyone holding one can
forge "I am healthy":

```bash
sudo nano /etc/bcss-monitoring.env
```

```bash
BASE_URL=https://app.bcss.ro
BACKUP_DIR=/var/lib/docker/volumes/bcsc-prod_backups/_data
COMPOSE_DIR=/opt/bcss
HC_PING_URL=https://hc-ping.com/<uuid-health>
HC_PING_URL_OFFSITE=https://hc-ping.com/<uuid-offsite>
HC_PING_URL_DRILL=https://hc-ping.com/<uuid-drill>
```

```bash
sudo chmod 600 /etc/bcss-monitoring.env
```

**3. Run each by hand first**, before trusting cron — the script working and
cron's environment working fail for different reasons:

```bash
sudo /opt/bcss/infrastructure/monitoring/cron-healthcheck.sh
sudo /opt/bcss/infrastructure/monitoring/check-offsite-freshness.sh
sudo /opt/bcss/scripts/restore-drill.sh
```

**4. `root`'s crontab** — root is required to read the backup volume and talk to
Docker. The scripts read `/etc/bcss-monitoring.env` themselves, so the lines stay
short:

```cron
*/5 * * * * /opt/bcss/infrastructure/monitoring/cron-healthcheck.sh >/dev/null 2>&1
0 5 * * * /opt/bcss/infrastructure/monitoring/check-offsite-freshness.sh >/dev/null 2>&1
0 6 1 * * /opt/bcss/scripts/restore-drill.sh >/dev/null 2>&1
```

A crontab entry must fit on a **single physical line**. The earlier form, with
`set -a; . /etc/…; set +a;` inline, exceeded 130 characters and split when
pasted — cron rejected it with `bad minute`. Hence the self-sourcing scripts.
Override the path with `BCSS_MONITORING_ENV=/other/file` if needed.

Confirm three entries installed, not six:

```bash
sudo crontab -l | grep -cE 'monitoring/|restore-drill'   # expect 3
```

**5. Prove alerting works by breaking something.** This is the step people skip:

```bash
sudo docker compose --env-file /opt/bcss/.env.prod -f /opt/bcss/compose.prod.yaml stop api
# wait ~10 min: healthchecks.io must go DOWN and email you
sudo docker compose --env-file /opt/bcss/.env.prod -f /opt/bcss/compose.prod.yaml start api
```

An untested alert is an assumption. A successful ping proves nothing about
whether failure reaches a human. *(Performed 2026-08-11; the alert arrived.)*

## 5. Responding to an alert

The failure output travels in the ping body, so the notification says what broke
rather than that something did. Then:

```bash
sudo tail -30 /var/log/bcss-healthcheck.log
sudo tail -20 /var/log/bcss-offsite-check.log
sudo tail -40 /var/log/bcss-restore-drill.log
```

| Alert | Likely cause | Next step |
|---|---|---|
| BCSS health down, no detail | Instance or network gone | Lightsail console |
| `liveness` / `readiness` FAIL | Backend down, or nginx pointing at a stale IP | [Troubleshooting](TROUBLESHOOTING.md) |
| `readiness/scanner` FAIL | ClamAV died or is still loading signatures | `dc logs clamav`; documents stall meanwhile |
| `backup … only 20B` | The empty-dump regression | [Backup and restore](BACKUP_AND_RESTORE.md) |
| `no backup in the last 26h` | The `backup` container died | `dc ps backup`, `dc logs backup` |
| Off-box check FAIL | Credentials rotated or bucket unreachable | Verify `OFFSITE_*` in `.env.prod` |
| Restore drill FAIL | The backup does not actually restore | Treat as an incident — read the log |

## 6. Logs

- `/var/log/bcss-healthcheck.log`
- `/var/log/bcss-offsite-check.log`
- `/var/log/bcss-restore-drill.log`
- Containers: `docker compose … logs <service>` — the Docker json-file driver
  handles rotation; no separate logrotate inside containers.

## 7. Not covered

- **No metrics or dashboards.** No Prometheus, no Grafana. The checks are
  pass/fail, not trended.
- **No log aggregation.** Logs live on the instance and are lost with it.
- **No application performance monitoring.** Slow endpoints surface only as user
  reports.
- **No certificate expiry alert.** Caddy renews automatically; a renewal failure
  would surface as a liveness failure once the certificate actually expires.

For a pilot this is a defensible level. All four are worth revisiting before
significant user load.
