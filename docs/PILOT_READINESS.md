# Feature behaviour and pilot hardening

This document describes the six hardening blocks delivered to stabilise the
system for a controlled internal pilot: the runtime behaviour and the reasoning
behind it. It introduces no new business modules — it brings the system to a
state where it can be started and run predictably.

For architecture see [Architecture](ARCHITECTURE.md); for operating it see
[Operations](OPERATIONS.md).

> Summary for operators: nothing stays blocked *silently*. Documents are scanned
> by a worker, notifications tell the truth about delivery, vehicle access is
> granted only with a code issued by the workshop, a TOTP code cannot be
> replayed, and readiness turns red when a critical dependency fails.

---

## Environment variables relevant to these behaviours

| Variable | Default | Role |
|---|---|---|
| `STORAGE_DRIVER` | `local` | `local` = private disk (dev/demo, persistent volume); `s3` = S3-compatible bucket (MinIO / AWS) in production |
| `S3_ENDPOINT` | `http://minio:9000` | S3/MinIO endpoint, used when `STORAGE_DRIVER=s3` |
| `S3_BUCKET` | `bcsc-documents` | The private document bucket |
| `S3_KEY` / `S3_SECRET` | *(empty)* | S3/MinIO access credentials |
| `S3_REGION` | `us-east-1` | Region used for the SigV4 signature (MinIO accepts any value) |
| `MESSENGER_TRANSPORT_DSN` | `auto_setup=1` (demo) / `auto_setup=0` (prod) | The async transport consumed by the worker. In production `auto_setup=0`: migrations own the schema (`messenger_messages` is created by `Version20260715234015`). Start-up ordering is guaranteed separately by the one-shot `migrate` service |
| `LEGACY_PLATE_CLAIM_ENABLED` | `false` | Claiming an account with only a registration plate — **disabled**, see Block 3. Do not enable |
| `APP_SECRET` | *(set it!)* | Readiness fails if it is empty or contains `change` / `dev-secret` |

The full list is in [`.env.prod.example`](../.env.prod.example).

---

## Block 1 — The Messenger worker

Antimalware scanning and notification sending are **asynchronous** jobs: they go
onto the `async` transport and must be consumed by a worker. Without one, an
uploaded document stays `PENDING` forever and is never servable.

- `compose.demo.yaml` has a `worker` service running `messenger:consume async`
  with `--time-limit` / `--memory-limit` and `restart: unless-stopped` — the
  process exits periodically and compose restarts it, so it never "dies quietly".
- Messages that fail `max_retries` times land on the `failed` transport
  (`doctrine://default?queue_name=failed`), where they can be inspected. They are
  not lost.

```bash
docker compose -f compose.demo.yaml logs -f worker
docker compose -f compose.demo.yaml exec backend php bin/console messenger:stats
docker compose -f compose.demo.yaml exec backend php bin/console messenger:failed:show
```

In production, ordering is enforced by the one-shot `migrate` service — see
[Architecture §6](ARCHITECTURE.md).

---

## Block 2 — Integrity of deadline verification

A deadline carries a **provenance** (`source` plus `verifiedAt` / `verifiedBy`).

- When a **customer** changes `validFrom`, `expiresAt` or the deadline's
  document, `source` becomes `CLIENT` and the verification resets
  (`verifiedAt` / `verifiedBy` → null) — **even if the row was `SERVICE` but
  unverified**. A value touched by the customer cannot remain marked "verified by
  the workshop".
- Editing **only the note** does not reset verification.
- **Admin** changes do not verify automatically — verification is set only by an
  explicit `verify: true` action.
- Every transition is audited with the old and new values plus the reason for the
  reset.

---

## Block 3 — Secure vehicle activation

A registration plate and a VIN **are not secrets**, so they no longer grant
access to an imported vehicle on their own. Instead:

1. The workshop issues an **activation code** for a vehicle
   (`POST /api/admin/vehicles/{id}/activation-token`). The code is shown **once**
   in the admin panel.
2. The code is random (128 bits), stored only as a **hash** (SHA-256), with an
   **expiry** (7 days), **single use**, and an **attempt limit** (a rate limit on
   `activation`).
3. The customer uses it at `POST /api/me/vehicles/activate`. A correct code links
   the vehicle to the customer's profile.
4. On an **ownership transfer**, the active ownership row is reassigned to the new
   owner and the previous owner's access is closed. Conflicts return `409`.
5. A wrong, expired or already-used code returns `422` with a generic message that
   does not disclose which of those it was.
6. Everything is audited (`vehicle.activation_issued`, `vehicle.activation_used`).

Legacy plate-only claiming remains in the code but is **disabled** behind
`LEGACY_PLATE_CLAIM_ENABLED=false`. Do not enable it.

---

## Block 4 — Honest notification state

State model: `PENDING → PROCESSING → { SENT | FAILED | MANUAL_ACTION_REQUIRED | SKIPPED }`.

- `SENT` **only** on a real success from an automated provider, **or** on an
  explicit manual confirmation by an admin. With no provider configured, a
  notification never becomes a blind `SENT` — it becomes
  `MANUAL_ACTION_REQUIRED` (or `SKIPPED` for internal addresses
  `@clienti.local` / `@anonim.local`).
- Delivery goes through a `NotificationDelivery` adapter. In the pilot the
  default implementation (`ManualNotificationDelivery`) sends nothing
  automatically; it is replaced when a real provider is configured.
- **Idempotency and deduplication**: notifications carry a `dedupKey`, and a
  terminal state short-circuits reprocessing. Retries happen only for automated
  providers and retriable failures.
- An admin can mark one as manually sent
  (`POST /api/admin/notifications/{id}/manually-sent`) with `sentBy` / `sentAt` /
  channel / note — audited.

Listing: `GET /api/admin/notifications` (service-admin role).

**This is the current state of notifications in production.** See
[Roadmap](ROADMAP.md) — it is the largest gap before real users.

---

## Block 5 — TOTP anti-replay protection

- The last accepted TOTP step is **persisted** per user (`users.last_totp_step`).
- A code with the **same step or older** is rejected — an intercepted code cannot
  be reused inside its validity window.
- Consumption is **concurrency-safe**: a conditional, atomic
  `UPDATE … WHERE last_totp_step IS NULL OR last_totp_step < :step`, so two
  parallel requests with the same code cannot both succeed.
- Backup codes are **single use** and stored hashed.
- A rejected replay is audited (`identity.2fa_replay_rejected`) while the customer
  sees a generic message.

---

## Block 6 — Durable storage and deep readiness

### Storage

- `STORAGE_DRIVER` switches between `local` and `s3` at runtime. `S3Storage`
  implements the AWS SigV4 signature directly (no SDK), with path-style
  addressing for MinIO compatibility. The bucket is private and serving goes
  through signed URLs plus an authorisation check, exactly as in the local
  variant.
- In `compose.demo.yaml`, documents live on the persistent `storage_data` volume
  shared by `backend` and `worker`, so they survive `up --build` and restarts.

### Liveness vs readiness — deliberately separate

- `GET /api/health` (**liveness**): the process is alive. It does **not** touch
  external dependencies, so a database outage does not trigger cascading
  restarts. Always `200`.
- `GET /api/health/ready` (**readiness**): the application can safely SERVE.
  It checks the **critical** dependencies — database, migration state, storage
  (a real write/read/delete probe), application secrets — plus `messenger` and
  `scanner`, which are non-critical. A failed critical dependency returns `503`.
  Per-check statuses: `ok` / `degraded` / `failed`.
- **Readiness is never green with a critical dependency down** — a default
  `APP_SECRET` or unapplied migrations produce `503`.
- `scanner` probes the ClamAV daemon with `PING`/`PONG` (a short 2s timeout, no
  file transfer). It is **non-critical** deliberately: the scanner is fail-closed,
  so if it dies, uploaded documents wait, but the rest of the API (reads,
  deadlines, history) stays servable and the instance should not leave rotation.
  Without this check the failure was completely silent. A dead ClamAV therefore
  shows `200` with `"status":"degraded"` and `checks.scanner.status = "failed"` —
  **that** is the signal to watch in monitoring, not the HTTP code.

```bash
curl -s https://app.bcss.ro/api/health          # {"status":"ok"}
curl -s https://app.bcss.ro/api/health/ready | python3 -m json.tool
```

### Backup and restore

Now covered in full by [Backup and restore](BACKUP_AND_RESTORE.md). In short: a
nightly backup with an off-box copy to Lightsail, verified by reading the upload
back, plus an automated monthly restore drill.

---

## Running the regression suite

```bash
./scripts/regression.sh
```

Runs: backend tests (PHPUnit), container lint (prod + test), YAML lint,
typecheck/lint/build for both frontends, and validation of all three docker
compose files. The Playwright e2e tests are separate — they need the stack
running; see [`../e2e/README.md`](../e2e/README.md).

---

## Known limitations

- **No automated notification provider.** Notifications reach
  `MANUAL_ACTION_REQUIRED`, not `SENT`, until a real provider is configured. A
  deliberate choice (Block 4), not a bug. See [Roadmap](ROADMAP.md).
- **`S3Storage` has not been validated against real AWS S3.** It *is* exercised
  against in-stack MinIO on every readiness probe, which performs a real
  write/read/delete — so the adapter works. What remains untested is AWS itself.
  *(An earlier version of this document said the driver had never been exercised
  at runtime at all; that is no longer accurate.)*
- **Email remains a manual step**, by product decision rather than omission.
