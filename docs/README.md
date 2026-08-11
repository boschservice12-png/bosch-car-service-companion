# Documentation

Start here.

**Language convention: all developer and operator documentation is English.**
Two deliberate exceptions — user-facing product strings, where Romanian is the
source language of the trilingual feature (see
[Architecture §9](ARCHITECTURE.md)), and
[`GHID_INSTALARE_COMPANION_RO.md`](GHID_INSTALARE_COMPANION_RO.md), which is
written for Romanian customers rather than developers.

---

## By task

| I want to… | Read |
|---|---|
| Understand how the system fits together | [Architecture](ARCHITECTURE.md) |
| Run the system day to day | [Operations](OPERATIONS.md) |
| Ship a change to production | [Deployment](DEPLOYMENT.md) |
| Back up, restore, or recover from disaster | [Backup and restore](BACKUP_AND_RESTORE.md) |
| Understand or install the alerting | [Monitoring](MONITORING.md) |
| Debug something behaving oddly | [Troubleshooting](TROUBLESHOOTING.md) |
| Know what is left to do | [Roadmap](ROADMAP.md) |
| Run the demo stack | [Demo](DEMO.md) |
| Call the API | [`api/openapi.yaml`](api/openapi.yaml) |

## Reference

| Subject | File |
|---|---|
| API contract (kept in sync with the router, enforced by `OpenApiSyncTest`) | [`api/openapi.yaml`](api/openapi.yaml) |
| Entity-relationship model | [`data-model/erd.md`](data-model/erd.md) |
| Architecture decision records | [`architecture/adr/`](architecture/adr/) |
| Security strategy | [`security/security-strategy.md`](security/security-strategy.md) |
| GDPR retention policy | [`security/data-retention-policy.md`](security/data-retention-policy.md) |
| Legal separation / scope boundary | [`legal-separation/scope.md`](legal-separation/scope.md) |
| Functional analysis (stage 1) | [`analysis/stage-1-functional-analysis.md`](analysis/stage-1-functional-analysis.md) |
| Pilot readiness blocks | [`PILOT_READINESS.md`](PILOT_READINESS.md) |
| PWA installation, developer notes | [`PWA_SIMPLE_INSTALLATION.md`](PWA_SIMPLE_INSTALLATION.md) |
| PWA installation, **for Romanian customers** | [`GHID_INSTALARE_COMPANION_RO.md`](GHID_INSTALARE_COMPANION_RO.md) |
| Email notification design notes | [`EMAIL_NOTIFICATION_TODO.md`](EMAIL_NOTIFICATION_TODO.md) |
| Browser e2e tests | [`../e2e/README.md`](../e2e/README.md) |
| Backup scripts and drill log | [`../infrastructure/backup/restore.md`](../infrastructure/backup/restore.md) |
| Backend module layout | [`../backend/src/README.md`](../backend/src/README.md) |

## Conventions

- **`main` is the production branch.** A push to it deploys.
  See [Deployment](DEPLOYMENT.md).
- **Migrations own the schema.** Not Messenger, not `schema:update`.
- **Never build images on the production box.** The pipeline builds on GitHub
  runners; the deploy script pulls.
- **Verify, do not assume.** Nine defects in this codebase reported success while
  doing nothing. Where a check exists, it asserts an outcome — an uploaded
  backup's size is re-read, a deploy compares running image tags, the restore
  drill actually restores. Keep new checks to that standard.
