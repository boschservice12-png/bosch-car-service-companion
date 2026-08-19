# ADR 0004 — Stack versions

- **Status:** Accepted — versions verified and pinned (2026-08-11)
- **Context:** The original brief required: "Do not pin unverified versions. At
  project start, check the stable and supported versions and document the
  choice." This ADR was provisional for a long time; it is now settled.

## Pinned versions

| Component | Version | Where it is pinned |
|---|---|---|
| PHP | 8.3 | `infrastructure/docker/backend.Dockerfile` (`php:8.3-fpm`), `demo/backend.Dockerfile` |
| Symfony | 7.x | `backend/composer.json` |
| Doctrine ORM | compatible with Symfony 7 | `backend/composer.json`, migrations enabled |
| PostgreSQL | 16 | `compose.prod.yaml`, `compose.demo.yaml` |
| Redis | 7 | `compose.prod.yaml` |
| Node.js | 20 (LTS) | `infrastructure/docker/frontend.Dockerfile` (`node:20-bookworm-slim`) |
| Next.js | 15.x (App Router) | `apps/*/package.json` |
| React | 19.x | `apps/*/package.json` |
| TypeScript | 5.7.x, strict, `noUncheckedIndexedAccess` | `apps/*/tsconfig.json` |
| nginx | 1.27 | `compose.prod.yaml` |
| Caddy | 2 | `compose.prod.yaml` |
| ClamAV | stable | `compose.prod.yaml` |
| MinIO server | `RELEASE.2025-09-07T16-13-09Z` | `compose.prod.yaml` |
| MinIO client (`mc`) | `RELEASE.2025-08-13T08-35-41Z` | `compose.prod.yaml`, `infrastructure/backup/Dockerfile` |

## Why pinning is not optional here

An earlier revision of this ADR *claimed* the PHP line was pinned while the
Dockerfile did not actually pin it. `FROM php:8-fpm` then floated to PHP 8.5,
where `pecl install redis` compiles but installs no module — breaking the
backend image entirely.

The same class of failure recurred later with two MinIO images that carried no
tag at all. See [Troubleshooting §9](../../TROUBLESHOOTING.md).

The lesson recorded here: **an ADR asserting a constraint is not the same as the
constraint being enforced.** When reviewing this file, check the referenced
configuration rather than trusting the table.

## Upgrade procedure

Third-party image upgrades are deliberately **not** part of a deploy — the
deploy script pulls only the seven images we build. To upgrade one:

1. Change the tag in `compose.prod.yaml` (and the backup Dockerfile, for `mc`).
2. Verify locally, including anything that parses that tool's output — the
   read-back verification in `backup-cron.sh` parses `mc stat --json`.
3. Deploy, then confirm the container is running the intended version.
