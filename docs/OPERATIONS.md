# Operations

The day-to-day runbook. For how the system is built see
[Architecture](ARCHITECTURE.md); for shipping changes see
[Deployment](DEPLOYMENT.md).

---

## 1. Where things are

| | |
|---|---|
| Customer app | https://app.bcss.ro |
| Admin portal | https://admin.app.bcss.ro |
| Liveness | https://app.bcss.ro/api/health |
| Readiness | https://app.bcss.ro/api/health/ready |
| Server | `ubuntu@54.93.39.7` (AWS Lightsail, eu-central-1) |
| Checkout | `/opt/bcss` |
| Secrets | `/opt/bcss/.env.prod` — chmod 600, gitignored, **the only copy** |
| Monitoring config | `/etc/bcss-monitoring.env` — chmod 600 |
| Off-box backups | Lightsail bucket `backup-bcss`, prefix `bcss/` |
| Container registry | `ghcr.io/boschservice12-png/bcsc-*` |

Every compose command below assumes:

```bash
cd /opt/bcss
```

and uses `--env-file .env.prod -f compose.prod.yaml`. To save typing:

```bash
alias dc='docker compose --env-file /opt/bcss/.env.prod -f /opt/bcss/compose.prod.yaml'
```

## 2. What runs automatically

Nothing in this table needs a human. All times UTC.

| Job | When | Where | Verifies itself by |
|---|---|---|---|
| Backup → local + Lightsail | 03:00 daily | `backup` service | Re-reading the upload and comparing sizes |
| GDPR purge (`app:gdpr:purge`) | 04:00 daily | `scheduler` service | Exit code |
| Health / readiness / disk / backup size | every 5 min | host cron | Dead man's switch to healthchecks.io |
| Off-box freshness | 05:00 daily | host cron | Fails if the newest bucket backup is >26h old |
| Restore drill | 06:00 on the 1st | host cron | Restores from the bucket and compares to production |
| Instance snapshot | daily | Lightsail | — |
| Deploy | every push to `main` | GitHub Actions | Asserts containers run the new image tag |

Migrations run on every deploy, via the one-shot `migrate` service.

## 3. Daily checks

Normally none — the alerting is push-based, so silence means healthy. If you
want to look anyway:

```bash
# Is everything up?
dc ps

# Deep readiness — all six checks should be "ok"
curl -s https://app.bcss.ro/api/health/ready | python3 -m json.tool

# Did last night's backup work?
dc logs backup --since 24h | tail -20
```

Readiness returns six checks. `database`, `migrations`, `storage` and `secrets`
are **critical** — any failure returns HTTP 503 and should pull the instance out
of rotation. `messenger` and `scanner` are **non-critical**: they return 200 with
`"status": "degraded"`.

That last point matters. **A dead ClamAV returns HTTP 200.** The signal is
`checks.scanner.status`, not the status code. `healthcheck.sh` inspects it; a
plain `curl -f` would not.

## 4. Common tasks

### Look at logs

```bash
dc logs backend --tail 100 -f
dc logs worker --tail 100 -f
dc logs backup --since 24h
dc logs migrate            # last deploy's migration run
```

### Restart a service

```bash
dc restart api             # e.g. after editing the nginx config
dc up -d --force-recreate backend
```

**Bind-mounted files need `restart`, not `up -d`.** Compose only recreates a
container when its image or spec changes, so editing
`infrastructure/nginx/default.conf` or any entrypoint script under
`infrastructure/docker/` does nothing until you restart that service. `up -d`
will report `Running` and change nothing.

### Run a console command

```bash
dc run --rm --no-deps -T --entrypoint sh backend -c 'php bin/console <command>'
```

Available application commands:

| Command | What it does |
|---|---|
| `app:deadlines:scan` | Computes which deadline notifications are due |
| `app:gdpr:purge` | Applies the retention policy (anonymise + clean logs) |
| `app:gdpr:cancel-deletion` | Cancels a deletion request inside the grace period |
| `app:user:create` | Creates a user (customer or admin) |
| `app:2fa:reset` | Resets TOTP + backup codes for a staff account (audited) |
| `app:demo:seed` | Seeds demo data (idempotent) — **not for production** |

### Trigger a backup by hand

```bash
dc run --rm -e BACKUP_ONESHOT=1 backup
```

Expect a real `database archive: <N> B` (thousands, not 20) and
`off-box verification OK`.

### Reset an admin's 2FA

```bash
dc run --rm --no-deps -T --entrypoint sh backend -c \
  'php bin/console app:2fa:reset admin@bcss.ro'
```

This is audited (`identity.2fa_reset` in `audit_logs`). Confirm the request came
from the person it claims to, out of band.

### Inspect the message queue

```bash
dc run --rm --no-deps -T --entrypoint sh backend -c 'php bin/console messenger:stats'
dc run --rm --no-deps -T --entrypoint sh backend -c 'php bin/console messenger:failed:show'
```

### Check disk

```bash
df -h /
docker system df
docker run --rm -v bcsc-prod_backups:/b alpine du -sh /b
```

## 5. Database access

```bash
# psql shell
dc exec db psql -U bcsc -d bcsc

# one-off query
dc exec -T db psql -U bcsc -t -A -d bcsc -c "SELECT count(*) FROM vehicles;"
```

Careful: `dc exec db` connects to the **production** database. For anything
exploratory, use the restore drill's throwaway database instead.

## 6. Signals worth watching

From the application logs and `audit_logs`:

| Signal | Where | Why it matters |
|---|---|---|
| Waves of `429` | nginx / application logs | Abuse of login, messages or upload |
| Repeated `403 two_factor_required` | application logs | Someone probing an admin account |
| `identity.2fa_reset`, `user.import_account_claimed` | `audit_logs` | Sensitive operations, review periodically |
| `5xx` with a `traceId` | application/problem+json | Correlating a user-reported incident |
| Documents with `scanStatus = INFECTED` | documents table | Malicious uploads |

## 7. Emergencies

| Situation | Go to |
|---|---|
| API returns 502, frontends fine | [Troubleshooting §1](TROUBLESHOOTING.md) |
| Backups look wrong or empty | [Backup and restore](BACKUP_AND_RESTORE.md) |
| Need to restore production | [Backup and restore §4](BACKUP_AND_RESTORE.md) |
| A deploy failed | [Deployment §5](DEPLOYMENT.md) |
| Alert fired from healthchecks.io | [Monitoring §4](MONITORING.md) |

The single most useful diagnostic, because it checks the same things the
monitoring does:

```bash
BASE_URL=https://app.bcss.ro bash /opt/bcss/infrastructure/monitoring/healthcheck.sh
```

## 8. Known operational constraints

- **RPO is 24 hours.** One backup a day at 03:00 UTC. A restore loses everything
  since then. This is a schedule decision, not a measurement — revisit it before
  real customer records accumulate.
- **Never build images on the production box.** Two Next.js compiles plus the PHP
  extension build peg both vCPUs for roughly 15 minutes on the machine serving
  the pilot. The deploy pipeline builds on GitHub runners for exactly this reason.
- **Rollback returns code, not schema.** `migrate` applies migrations on every
  deploy. A destructive migration needs a restore, not a rollback.
- **ClamAV has no arm64 image.** It cannot run on an Apple Silicon machine, so
  the scanner path cannot be exercised locally there. Production is x86_64.
