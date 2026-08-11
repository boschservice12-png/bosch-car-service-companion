# Monitoring

The monitoring documentation now lives at
[`docs/MONITORING.md`](../../docs/MONITORING.md) — it was moved so that all
operator documentation sits together under `docs/`.

This directory holds the scripts themselves:

| Script | Purpose |
|---|---|
| `healthcheck.sh` | The checks: liveness, readiness (including the non-critical probes), disk, backup age and size |
| `cron-healthcheck.sh` | Five-minute wrapper implementing the dead man's switch |
| `check-offsite-freshness.sh` | Daily check that a recent backup exists in the off-box bucket |

The monthly restore drill is
[`../../scripts/restore-drill.sh`](../../scripts/restore-drill.sh).
