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

- **Automata napi backup**: a `backup` szolgáltatás a stack része — minden nap
  03:00 UTC-kor `pg_dump`-ol ÉS `mc mirror`-ral menti a MinIO dokumentumtárat a
  `backups` volume-ra, integritás-ellenőrzéssel és 14 napos retencióval. Nincs
  külön teendő; az órát/retenciót a compose `backup` env-jei állítják.
  ```bash
  docker compose --env-file .env.prod -f compose.prod.yaml logs -f backup   # figyelés
  docker compose --env-file .env.prod -f compose.prod.yaml run --rm \
    -e BACKUP_ONESHOT=1 backup                                              # azonnali mentés (fut+kilép)
  ```
  > A `backups` volume-ot rendszeresen másold **külső** helyre (másik gép /
  > objektumtár) — egy gépen tartott mentés nem katasztrófa-biztos.
- **A visszaállítást havonta próbáld ki** izolált környezetben:
  `infrastructure/backup/restore.sh` + `restore.md` (RTO/RPO feljegyezve). A
  mentés `db.sql.gz` + `documents.tar.gz` párost tartalmaz mentési mappánként.
- GDPR-purge napi cron:
  ```bash
  docker compose --env-file .env.prod -f compose.prod.yaml exec backend \
    php bin/console app:gdpr:purge
  ```

## 6. Frissítés (új verzió kihúzása)

### 6.a Automatikus deploy (alapértelmezett)

Push a `main`-re elindítja a `.github/workflows/deploy.yml`-t: lefuttatja a
backend teszteket, megépíti a négy image-et a GitHub runnerein, feltölti őket a
GHCR-be a commit SHA-jával címkézve, majd SSH-val lefuttatja a szerveren a
`scripts/deploy-remote.sh`-t (mentés → pull → `up -d` → ellenőrzés).

**Image soha nem épül a produkciós gépen.** Két Next.js fordítás és a PHP
kiterjesztések ~15 percig lefoglalják mindkét vCPU-t, ugyanazon a gépen, amelyik
a pilotot szolgálja ki.

Egyszeri beállítás:

1. **Repo secretek** (Settings → Secrets and variables → Actions):

   | Secret | Érték |
   |---|---|
   | `DEPLOY_HOST` | `54.93.39.7` |
   | `DEPLOY_USER` | `ubuntu` (opcionális, ez az alapértelmezés) |
   | `DEPLOY_SSH_KEY` | egy **erre a célra** generált privát kulcs (lásd lent) |
   | `DEPLOY_KNOWN_HOSTS` | `ssh-keyscan -H 54.93.39.7` kimenete |

   A deploy kulcsot külön generáljuk, nem a személyes kulcsot használjuk:

   ```bash
   ssh-keygen -t ed25519 -f ~/bcss-deploy -C "github-actions-deploy" -N ""
   ssh-copy-id -i ~/bcss-deploy.pub ubuntu@54.93.39.7
   cat ~/bcss-deploy          # ez megy a DEPLOY_SSH_KEY secretbe
   ssh-keyscan -H 54.93.39.7  # ez megy a DEPLOY_KNOWN_HOSTS secretbe
   rm ~/bcss-deploy           # a szerveren és a secretben már megvan
   ```

   A `DEPLOY_KNOWN_HOSTS`-ot szándékosan secretként tároljuk, nem `ssh-keyscan`-nel
   szerezzük deploy közben: ha bármilyen felkínált kulcsot elfogadunk, a
   host-ellenőrzésnek nincs értelme.

2. **A szerver bejelentkezése a GHCR-be** (egyszer, `read:packages` jogú PAT-tal):

   ```bash
   echo <PAT> | docker login ghcr.io -u <github-felhasznalo> --password-stdin
   ```

   Enélkül a `docker compose pull` 401-gyel elhasal, a deploy pedig — helyesen —
   megáll, mielőtt bármit elindítana.

3. Az **`production` environment** a repo beállításaiban: itt lehet kötelező
   jóváhagyást kérni deploy előtt, ha szeretnétek.

### 6.b Kézi deploy (vagy ha a CI nem elérhető)

Ugyanaz a szkript, ugyanazok a lépések:

```bash
cd /opt/bcss
IMAGE_TAG=<sha> bash scripts/deploy-remote.sh
```

Vagy a régi, teljesen kézi út (image-ek helyben épülnek — **lassú, kerülendő**):

```bash
git pull
docker compose --env-file .env.prod -f compose.prod.yaml up -d --build
```

A migrációkat egy külön, egyszer lefutó `migrate` szolgáltatás futtatja: elindul,
alkalmazza a migrációkat, majd 0-s kóddal kilép. A `backend` és a `worker` csak
ezután indul (`depends_on: condition: service_completed_successfully`), így a
worker soha nem előzheti meg a sémát. A backend entrypoint már csak a cache-t
melegíti újra. Nulla-leállásos deploy nincs a pilotban (rövid újraindítás
elfogadható).

Ha a `migrate` nem nullával lép ki, a `backend` és a `worker` **el sem indul** —
a hibás migráció így látható leáll, nem pedig félig migrált sémán futó szolgáltatás.
Ellenőrzés: `docker compose --env-file .env.prod -f compose.prod.yaml logs migrate`.

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
