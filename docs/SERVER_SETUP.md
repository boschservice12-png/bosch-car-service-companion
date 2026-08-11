# Server setup (first-time provisioning)

Bringing up the production stack on a **new** machine. For ongoing deploys to an
existing server see [Deployment](DEPLOYMENT.md); for running it see
[Operations](OPERATIONS.md).

The stack is hosting-neutral: it runs on a plain cloud VPS, an on-premise
server, or anywhere with Docker and Docker Compose. The current pilot runs on
AWS Lightsail — see [Architecture §11](ARCHITECTURE.md).

---

## 0. Prerequisites

- Docker Engine + Docker Compose v2.
- ~2 vCPU / 4 GB RAM is enough for an internal pilot (ClamAV is the hungriest,
  around 1 GB). The current pilot uses 2 vCPU / 8 GB.
- Outbound 443 for Let's Encrypt, if starting with a domain.
- Inbound 22, 80, 443 only.
- **Optionally** a domain for two hostnames (customer + admin). Without one, IP
  and plain HTTP work and TLS can be added later.

## 1. Code and configuration

```bash
git clone git@github.com:boschservice12-png/bosch-car-service-companion.git bcss
cd bcss
git checkout main
cp .env.prod.example .env.prod
chmod 600 .env.prod
```

Fill in every `<CHANGE-ME>` field in `.env.prod`:

| Field | Value |
|---|---|
| `APP_SECRET` | `openssl rand -hex 32`. Readiness fails on a default or "change"-containing value |
| `POSTGRES_PASSWORD` + `DATABASE_URL` | The same strong password in both places |
| `MINIO_ROOT_USER` = `S3_KEY` | Any identifier, identical in both |
| `MINIO_ROOT_PASSWORD` = `S3_SECRET` | A strong password, identical in both |
| `CUSTOMER_SITE` / `ADMIN_SITE` | With a domain: the hostnames. With an IP: `:80` / `:8081` |
| `CORS_ALLOW_ORIGIN`, `CSRF_TRUSTED_ORIGINS` | The public origins, when using domains |
| `OFFSITE_*` | Off-box backup target — see [Backup and restore](BACKUP_AND_RESTORE.md) |

`.env.prod` is gitignored and is **the only copy** of these generated
credentials. Keep it `chmod 600` and back it up somewhere a human can reach
without the server.

## 2. Start

```bash
docker compose --env-file .env.prod -f compose.prod.yaml up -d --build
```

`--build` is correct **only** on first bring-up, when no images exist in GHCR
yet. Afterwards, deploys pull pre-built images — see [Deployment](DEPLOYMENT.md).
Building here takes ~15 minutes and saturates both vCPUs.

What happens: MinIO starts → `minio-setup` creates the private bucket → `migrate`
waits for the database and applies migrations, then exits 0 → `backend`,
`worker` and `scheduler` start → the frontends run in built mode → Caddy
terminates TLS.

The first ClamAV start takes several minutes while it downloads virus
definitions. `checks.scanner` in readiness will be red until it finishes.

## 3. First admin account

There is no built-in admin. Create one from the console:

```bash
docker compose --env-file .env.prod -f compose.prod.yaml exec backend \
  php bin/console app:user:create --admin --env=prod -- admin@bcss.ro 'StrongPassword123!'
```

On first login the admin is sent to **2FA enrolment** (`/securitate`). With
`APP_ENV=prod`, TOTP enrolment is mandatory and `/api/admin` routes are blocked
until it is complete. Enrolment issues the TOTP secret and hashed backup codes.

## 4. Verification

```bash
# All containers running; db and clamav healthy; migrate and minio-setup exited 0
docker compose --env-file .env.prod -f compose.prod.yaml ps -a

# Liveness and deep readiness from outside (the frontend proxies /api)
curl -s https://<customer-host>/api/health
curl -s https://<customer-host>/api/health/ready | python3 -m json.tool

# Without a domain (IP + HTTP)
curl -s http://<server-ip>/api/health
```

Readiness should report six checks. `scanner` may be red for the first few
minutes while ClamAV loads its databases.

## 5. Post-setup

None of these are optional before real users:

1. **Off-box backups** — configure `OFFSITE_*` and verify with a one-shot backup.
   [Backup and restore §3](BACKUP_AND_RESTORE.md)
2. **Monitoring and alerting** — three healthchecks.io checks and three cron
   entries, then break something to prove the alerting works.
   [Monitoring §4](MONITORING.md)
3. **The deploy pipeline** — repository secrets and a GHCR login on the server.
   [Deployment §4](DEPLOYMENT.md)
4. **Instance snapshots** — daily, in your provider's console. Backups protect
   data; snapshots protect the machine.

## 6. Starting without a domain

Leave `CUSTOMER_SITE=:80` and `ADMIN_SITE=:8081`. The customer app is then at
`http://<server-ip>/` and the admin at `http://<server-ip>:8081/`.

**The 8081 port mapping was removed from `compose.prod.yaml`** because the pilot
runs on domains. Re-add it under the `caddy` service for this mode:

```yaml
    ports:
      - "80:80"
      - "443:443"
      - "8081:8081"
```

To add TLS later: set the two hostnames in `.env.prod`, point DNS at the server,
and `up -d`. Caddy requests Let's Encrypt certificates automatically.

## 7. Known limitations of the pilot bundle

- **Single machine.** No high availability; a host failure is downtime.
- **No automated notifications** — see [Roadmap](ROADMAP.md).
- **RPO 24 hours.** One backup a day.
- **Deploys cause a brief 502** (~10s) while the backend is recreated.
- **MinIO in-stack, not AWS S3.** The `S3Storage` adapter is unvalidated against
  real AWS; switching is an environment-variable change but currently untested.
