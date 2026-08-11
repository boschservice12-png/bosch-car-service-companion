# Roadmap and open items

What is left, honestly ordered. Anything marked **blocker** should be closed
before real customers use the system.

Last reviewed: 2026-08-11.

---

## Blockers before real users

### Automated notifications — not implemented

**The system sends no automated notifications.** Every notification, including
the deadline warnings for roadworthiness tests, insurance and road tax — the main
reason a customer installs the app — stops at `MANUAL_ACTION_REQUIRED` and waits
for a human to send it.

Already in place:

- `NotificationDelivery` (`backend/src/Notification/`), the contract
- `ManualNotificationDelivery`, which marks `MANUAL_ACTION_REQUIRED` / `SKIPPED`
  and never a blind `SENT`
- `app:deadlines:scan`, which correctly computes what is due and when

Missing: a real provider (SMTP/SES/Postmark for email, a push provider for the
PWA) and a cost/GDPR decision about the channel. This is a product decision, not
an infrastructure gap.

### The end-user install guide exists only in Romanian

`GHID_INSTALARE_COMPANION_RO.md` is written for customers, correctly in Romanian.
But the product ships a Hungarian and English UI, so Hungarian- and
English-speaking customers have no install guide. Either translate it or decide
explicitly that the pilot is Romanian-only.

---

## Should do soon

### Re-run the restore drill once real data exists

The drill passes, but production currently holds one user and no vehicles or
documents, so its row-count assertions pass trivially. It proves the mechanism,
not behaviour at volume. The monthly cron re-checks automatically as data
arrives — the point is to *look* at the first run that has real data in it.

### Confirm the 24-hour RPO is acceptable

One backup a day at 03:00 UTC, so a restore loses up to a day of work.
Structural, not a measurement. For customer vehicle records this deserves an
explicit decision; if unacceptable, the options are more frequent backups or
continuous WAL archiving.

### Playwright e2e against the full stack

The deploy gate runs backend tests and both frontend builds. Nothing exercises a
real browser journey — login, vehicle view, document download. Scaffolding
exists in `e2e/`.

---

## Worth doing

### `S3Storage` against real AWS

The adapter is exercised against in-stack MinIO on every readiness probe
(`checks.storage` performs a real write/read/delete), so it works. What is
unproven is real AWS S3. Migration would be an environment-variable change, but
Lightsail has no IAM instance roles, so it would mean long-lived keys.

### Application source comments are still Romanian

Documentation, configuration and scripts are now English throughout. Comments
inside `backend/src/**/*.php` and `apps/**/*.tsx` are not.

This is deliberately deferred: in those files Romanian text is frequently a
user-facing label or an i18n dictionary key rather than a comment, and Romanian
is the **source language** of the trilingual feature. A careless sweep would
break Hungarian and English. Doing it properly means separating comments from
strings file by file.

### Observability

No metrics, no dashboards, no log aggregation, no APM. Checks are pass/fail and
logs die with the instance. Defensible for a pilot; revisit under real load.

### Zero-downtime deploys

A deploy currently causes a brief 502 while the backend is recreated (~10s).
Accepted for the pilot.

---

## Done — kept for context

Closed on 2026-08-11 unless noted. Each links to the detail.

| Item | Where it is written up |
|---|---|
| Off-box backups to Lightsail, with read-back verification | [Backup and restore](BACKUP_AND_RESTORE.md) |
| Restore exercised end to end, including the disaster path | [`../infrastructure/backup/restore.md`](../infrastructure/backup/restore.md) |
| Automated monthly restore drill | [Backup and restore §6](BACKUP_AND_RESTORE.md) |
| `app:gdpr:purge` on a daily schedule | [Operations §2](OPERATIONS.md) |
| Dead man's switch monitoring, alerting break-tested | [Monitoring](MONITORING.md) |
| GHCR build-and-deploy pipeline | [Deployment](DEPLOYMENT.md) |
| Worker start-up ordering via the one-shot `migrate` service | [Architecture §6](ARCHITECTURE.md) |
| ClamAV added to readiness as a non-critical check | [Monitoring §3](MONITORING.md) |
| Lightsail daily snapshots | enabled in the console |
| All base images pinned | [Troubleshooting §9](TROUBLESHOOTING.md) |
| `compose.prod.yaml` validated by `regression.sh` | `scripts/regression.sh` |
| Dead Caddy 8081 publish removed | `compose.prod.yaml` |
| Documentation, configuration and scripts normalised to English | this branch |

### Defects found and fixed along the way

All nine reported success while doing nothing; all are documented with their
mechanism in [Troubleshooting](TROUBLESHOOTING.md):

1. `pg_dump` rejected the documented DSN → seven nights of empty backups
2. A pipeline exit code masked that failure, and `gzip -t` certified the result
3. Restore looked for the wrong filename and exited 0 having dropped all documents
4. An empty document bucket aborted the entire backup, database included
5. No CA bundle in the backup image → every HTTPS upload failed
6. nginx cached a stale backend IP → 502 on every deploy
7. The deploy pipeline reported success without deploying
8. The backup freshness check watched the wrong directory
9. Two MinIO images were entirely unpinned

---

## Explicitly not planned

- **Native mobile apps.** The customer app is an installable PWA; that is the
  product decision.
- **Plate-only account claiming.** `LEGACY_PLATE_CLAIM_ENABLED=false` and it
  stays false — a registration plate is not authentication.
- **Moving off Lightsail.** A deliberate cost decision for the pilot. Snapshots
  can be exported to EC2 if it converts.
