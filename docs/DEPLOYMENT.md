# Deployment

`main` is the production branch. **A push to `main` deploys to production.**

---

## 1. How a deploy works

`.github/workflows/deploy.yml` runs three jobs in sequence:

```
test ──▶ build (×4, parallel) ──▶ deploy
```

1. **test** — `composer install`, container lint in prod and test, YAML lint, and
   the full PHPUnit suite. This gate exists inside the deploy workflow because
   `backend-ci.yml` is path-filtered and therefore does not run on every push to
   `main`. A deploy cannot rely on a workflow that might not have run.
2. **build** — builds four images on GitHub runners and pushes them to GHCR,
   tagged with the commit SHA and `latest`:
   `bcsc-backend`, `bcsc-customer-web`, `bcsc-service-admin`, `bcsc-backup`.
   `worker`, `migrate` and `scheduler` all reuse the backend image.
3. **deploy** — copies `scripts/deploy-remote.sh` to the server and runs it.

All four images are rebuilt every time, even when only one changed. Otherwise
the current SHA tag would not exist for the unchanged services and
`docker compose pull` would fail on the server. GHA layer caching keeps the
unchanged builds cheap.

**Images are never built on the production machine.** Two Next.js compiles plus
the PHP extension build peg both vCPUs for ~15 minutes on the box serving the
pilot.

## 2. What happens on the server

`scripts/deploy-remote.sh` — kept in the repo rather than inline in YAML so it
can be read, reviewed, and run by hand identically.

| Step | Action | Why |
|---|---|---|
| 0 | Refuse if the working tree is dirty | `git reset --hard` would silently discard someone's hand-edit |
| 1 | `git reset --hard <SHA>` | The exact commit the images came from, so bind-mounted files (nginx config, entrypoints) match the images |
| 2 | One-shot backup | `migrate` applies migrations automatically, so a deploy can change the schema irreversibly. Back up first |
| 3 | `docker compose pull <our services only>` | Explicit, before `up`, with `set -e` |
| 4 | `docker compose up -d` | |
| 5 | **Verify the running image tags** | "The site is up" is not "the new version shipped" |
| 6 | Verify `migrate` exited 0 | |
| 7 | `healthcheck.sh` | The same definition of healthy the monitoring uses |

Two of these deserve emphasis.

**Step 3 pulls only our seven services.** An argument-less `pull` fetches
everything, including `postgres:16`, `redis:7`, `clamav:stable` and the MinIO
images — all floating tags. A new digest makes compose recreate the container,
which means every deploy would quietly upgrade the database and the antivirus.
Upgrading third-party components must be a separate, deliberate act.

**Step 5 is the one that catches a lying deploy.** It compares each container's
image tag against the SHA being deployed. Without it, a deploy that did nothing
at all can pass every health check — because the old containers are perfectly
healthy.

## 3. Deploying

Normally: merge to `main` and watch
[the Actions tab](https://github.com/boschservice12-png/bosch-car-service-companion/actions).

Manually, or if CI is unavailable:

```bash
cd /opt/bcss
IMAGE_TAG=<sha> bash scripts/deploy-remote.sh
```

To redeploy a specific commit, use the workflow's `workflow_dispatch` input.

Expect a brief 502 during step 4 while the backend is recreated and nginx
re-resolves its upstream (~10s). Zero-downtime deployment is not implemented for
the pilot; a short restart is accepted.

## 4. One-time setup

Required before the pipeline can work. All of this is already done for the
current server — this section is for rebuilding it.

**Repository secrets** (Settings → Secrets and variables → Actions → *Secrets*,
under *Repository secrets*):

| Secret | Value |
|---|---|
| `DEPLOY_HOST` | `54.93.39.7` |
| `DEPLOY_USER` | `ubuntu` (optional; this is the default) |
| `DEPLOY_SSH_KEY` | A private key generated **for this purpose**, not a personal one |
| `DEPLOY_KNOWN_HOSTS` | The server's host key |

Generate them on the server:

```bash
ssh-keygen -t ed25519 -f ~/gh-deploy -N "" -C "github-actions-deploy"
cat ~/gh-deploy.pub >> ~/.ssh/authorized_keys
cat ~/gh-deploy                                                    # DEPLOY_SSH_KEY
echo "54.93.39.7 $(cut -d' ' -f1,2 /etc/ssh/ssh_host_ed25519_key.pub)"   # DEPLOY_KNOWN_HOSTS
rm ~/gh-deploy ~/gh-deploy.pub
```

Reading the host key from `/etc/ssh/` beats `ssh-keyscan`: a scan trusts whatever
answers on the network. `DEPLOY_KNOWN_HOSTS` is a secret rather than a
scan-at-deploy-time for the same reason — accepting whatever key is presented at
that moment defeats host verification.

**GHCR login on the server** (one-time, with a classic PAT scoped to
`read:packages` only):

```bash
read -rsp 'PAT: ' PAT && echo "$PAT" | docker login ghcr.io -u <github-user> --password-stdin; unset PAT
```

The `read -rsp` form keeps the token out of `~/.bash_history`. Without this login,
`docker compose pull` returns 401 and the deploy stops — correctly — before
starting anything.

**Optional:** configure the `production` environment in repository settings with
a required reviewer if you want deploys to wait for approval.

## 5. When a deploy fails

The script prints the rollback commands with the real previous SHA filled in:

```bash
cd /opt/bcss
git reset --hard <previous-sha>
IMAGE_TAG=<previous-sha> docker compose --env-file .env.prod -f compose.prod.yaml up -d
```

**That reverts code only.** Migrations applied by `migrate` are not rolled back.
If a destructive migration ran, you need [a restore](BACKUP_AND_RESTORE.md) — and
the deploy took a backup at step 2 precisely for this.

Failure modes and what they mean:

| Symptom | Cause |
|---|---|
| Step 0 aborts, "working tree modified" | Someone edited files on the server. Reconcile before deploying |
| Step 3 401 | The server is not logged in to GHCR, or the package is not visible to it |
| Step 5 reports STALE containers | `up -d` did not replace them — check the pull output above it |
| Step 6 non-zero migrate | A migration failed. `backend` and `worker` did not start. Read `logs migrate` |
| Step 7 timeout on `/api/health` | The backend is not serving. Check `logs backend`, then [Troubleshooting](TROUBLESHOOTING.md) |

## 6. Rollback vs restore

| Problem | Action |
|---|---|
| Bad application code | Roll back the image tag (above) |
| Bad but non-destructive migration | Roll back code, write a corrective migration |
| Destructive migration, data lost | [Restore](BACKUP_AND_RESTORE.md) — rollback cannot help |

## 7. Branching

- `main` is production.
- Feature work branches from `main` and merges back via PR.
- `claude/pilot-readiness` is the historical development branch; `main` was
  created from it. It is behind and should not be deployed.
