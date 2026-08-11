# Run the demo with a single command (Docker)

Starts the **entire application** (database + backend + both frontends) locally,
with demo data already loaded. The only requirement is **Docker Desktop** (or
Docker Engine plus the `compose` plugin). PHP, Node and PostgreSQL do not need to
be installed.

## Start

From the project root:

```bash
docker compose -f compose.demo.yaml up --build
```

The first start takes a few minutes (it builds the backend image and installs the
frontend dependencies). When it finishes:

| Application | URL | Description |
|---|---|---|
| **Customer** (PWA) | http://localhost:3000 | the customer application |
| **Service / admin** | http://localhost:3001 | the workshop portal |
| Backend API | http://localhost:8080/api/health | optional, for checking |

> **Tip:** open the customer and admin apps in **two different browser profiles**
> (or one normal and one incognito) so the sessions do not overwrite each other.

## Demo accounts

| Role | Email | Password |
|---|---|---|
| Service (admin) | `admin@bcsc.ro` | `Demo1234!` |
| Customer | `client@bcsc.ro` | `Demo1234!` |

After logging in, both interfaces are already populated (deadlines, service
history, quotes, roadside assistance, mobility, damage claim, taxes). The
step-by-step scenario is in [`../docs/DEMO.md`](../docs/DEMO.md).

## Stop / restart

```bash
# Stop (keeps the data):        Ctrl+C, then
docker compose -f compose.demo.yaml down

# Full removal (including the database, for a clean start):
docker compose -f compose.demo.yaml down -v
```

Demo data is recreated automatically at start-up (`app:demo:seed` is idempotent).

## What starts

- **db** — PostgreSQL 16.
- **backend** — Symfony (built-in server on `:8080`); at start-up it applies
  migrations and runs `app:demo:seed`.
- **worker** — the Messenger consumer for the `async` transport. Without it,
  documents would stay `PENDING` forever.
- **customer-web** — Next.js dev on `:3000`, proxying `/api` → `backend:8080`.
- **service-admin** — Next.js dev on `:3001`, proxying `/api` → `backend:8080`.

Antimalware scanning of documents is asynchronous. In the demo the non-production
adapter marks files as clean, so downloads work immediately. Production uses
ClamAV and is fail-closed — see
[Architecture §7](../docs/ARCHITECTURE.md).

## Differences from production

This stack is **not** a production replica:

| | Demo | Production |
|---|---|---|
| `APP_ENV` | `dev` | `prod` |
| Storage | local disk | MinIO (S3) |
| Antimalware | permissive stub | ClamAV, fail-closed |
| TLS | none | Caddy + Let's Encrypt |
| Migrations | backend entrypoint | one-shot `migrate` service |
| Frontends | `next dev` | `next build` + `next start` |

Run it periodically anyway — drift between host and container has already caused
one production-only failure.
