# Telepítési runbook — pilot (production bundle)

Ez az útmutató a `compose.prod.yaml` production stack elindítását írja le egy
kontrollált belső pilothoz. Hosting-semleges: fut egy egyszerű felhős VPS-en,
saját/Bosch on-prem szerveren vagy bárhol, ahol van Docker + Docker Compose.

> A csomag: PostgreSQL 16 · Redis · MinIO (S3 dokumentumtár) · ClamAV ·
> backend (PHP-FPM) + **worker** · buildelt customer-web + service-admin ·
> Caddy (TLS-terminálás). A `.env.prod` sosem kerül gitbe.

---

## 0. Előfeltételek

- Docker Engine + Docker Compose v2 a szerveren.
- ~2 vCPU / 4 GB RAM elég egy belső pilotra (a ClamAV a legéhesebb, ~1 GB).
- Kimenő 443 (Let's Encrypt), ha doménnel indulsz.
- **Opcionális domén** két hostnévhez (kliens + admin). Domén nélkül IP + HTTP
  megy, TLS-t később kötsz be.

## 1. Kód + konfiguráció

```bash
git clone <repo> bcsc && cd bcsc
git checkout claude/pilot-readiness      # amíg nincs main-be mergelve
cp .env.prod.example .env.prod
```

Töltsd ki a `.env.prod` minden `<SCHIMBAȚI>` mezőjét:

| Mező | Mit |
|---|---|
| `APP_SECRET` | `openssl rand -hex 32` |
| `POSTGRES_PASSWORD` + `DATABASE_URL` | ugyanaz az erős jelszó mindkét helyen |
| `MINIO_ROOT_USER` = `S3_KEY` | tetszőleges azonosító, mindkettő egyenlő |
| `MINIO_ROOT_PASSWORD` = `S3_SECRET` | erős jelszó, mindkettő egyenlő |
| `CUSTOMER_SITE` / `ADMIN_SITE` | doménnel: hostnevek; IP-vel: `:80` / `:8081` |
| `CORS_ALLOW_ORIGIN`, `CSRF_TRUSTED_ORIGINS` | a publikus origin(ok) doménnel |

## 2. Indítás

```bash
docker compose --env-file .env.prod -f compose.prod.yaml up -d --build
```

Ami történik: MinIO elindul → `minio-setup` létrehozza a privát bucketet →
a **backend** megvárja a DB-t, lefuttatja a **migrációkat** (idempotens),
bemelegíti a cache-t, majd php-fpm-et indít → a worker csatlakozik →
a frontendek buildelt módban futnak → a Caddy terminál.

Első ClamAV-indulás több percig tart (vírusadatbázis letöltése).

## 3. Első admin-fiók

A rendszerben nincs beépített admin. Hozz létre egyet a konzolról a beépített
`app:user:create` paranccsal (a `--admin` kapcsoló ad SERVICE_ADMIN szerepet):

```bash
docker compose --env-file .env.prod -f compose.prod.yaml exec backend \
  php bin/console app:user:create --admin --env=prod -- admin@szerviznev.ro 'ErősJelszó123!'
```

Első belépéskor az admin a **2FA-beléptetéshez** (`/securitate`) kerül —
`APP_ENV=prod`-ban a TOTP-beléptetés kötelező (P0-06). A beléptetés után
kapja meg a TOTP-titkot és a hashelt tartalék-kódokat.

## 4. Ellenőrzés (deploy után)

```bash
# Konténerek állapota (mind „running", a db/clamav „healthy"):
docker compose --env-file .env.prod -f compose.prod.yaml ps

# Liveness + mély readiness kívülről (a frontend proxy-zza az /api-t):
curl -fsS https://<customer-domén>/api/health                                        # {"status":"ok"}
curl -s -o /dev/null -w "%{http_code}\n" https://<customer-domén>/api/health/ready   # 200

# Domén nélkül (IP + HTTP):
curl -s -o /dev/null -w "%{http_code}\n" http://<szerver-IP>/api/health/ready        # 200
```

A readiness JSON tartalma `{status, ready, checks{database, migrations, storage,
secrets, messenger}}` — kritikus függőség hibája **503**-at ad (szándékos).

- Kliens app: `https://<CUSTOMER_SITE>` · Admin: `https://<ADMIN_SITE>`.
- Worker fut: `... logs -f worker` és `... exec backend php bin/console messenger:stats`.
- Readiness **503**, ha kritikus függőség hibás (pl. alapértelmezett `APP_SECRET`,
  nem alkalmazott migráció) — ez szándékos.

## 5. Backup + visszaállítás

- Napi backup: futtasd a hoston az `infrastructure/backup/backup.sh`-t a
  `db` konténer `DATABASE_URL`-jével (a hoston telepített `pg_dump`-fal, vagy
  `docker compose exec db pg_dump`-on át). A MinIO dokumentumtárat `mc mirror`-ral
  mentsd egy külső célra. Részletek + retenció: `infrastructure/backup/backup.sh`.
- **A visszaállítást havonta próbáld ki** izolált környezetben:
  `infrastructure/backup/restore.sh` + `restore.md` (RTO/RPO feljegyezve).
- GDPR-purge napi cron:
  ```bash
  docker compose --env-file .env.prod -f compose.prod.yaml exec backend \
    php bin/console app:gdpr:purge
  ```

## 6. Frissítés (új verzió kihúzása)

```bash
git pull
docker compose --env-file .env.prod -f compose.prod.yaml up -d --build
```

A backend entrypoint minden induláskor lefuttatja az új migrációkat és
újramelegíti a cache-t. Nulla-leállásos deploy nincs a pilotban (rövid
újraindítás elfogadható).

## 7. Domén nélküli indulás (IP)

Hagyd `CUSTOMER_SITE=:80` / `ADMIN_SITE=:8081` értéken. A kliens app a
`http://<szerver-IP>/`, az admin a `http://<szerver-IP>:8081/` címen érhető el.
TLS-hez később: állítsd a doméneket a `.env.prod`-ban, mutasson a DNS a
szerverre, `... up -d` — a Caddy automatikusan kér Let's Encrypt tanúsítványt.

---

## Ismert korlátozások (pilot)

- **Nincs automata értesítési provider**: az értesítések `MANUAL_ACTION_REQUIRED`
  állapotig jutnak (terméki döntés). E-mail is manuális.
- **Egyetlen szerver, egyetlen worker** — nincs vízszintes skálázás; belső
  pilotra elég. Terheléskor a workert kell először skálázni.
- A `compose.prod.yaml` a `db`/`minio`/`redis` szolgáltatásokat is a stackben
  futtatja. Nagyobb terhelésnél ezeket érdemes managed szolgáltatásra cserélni
  (managed Postgres + S3) — a `STORAGE_DRIVER=s3` és a `DATABASE_URL` már erre
  kész.
