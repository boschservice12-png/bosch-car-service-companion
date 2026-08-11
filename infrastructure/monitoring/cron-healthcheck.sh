#!/usr/bin/env bash
# Cron wrapper around healthcheck.sh, implementing a dead man's switch.
#
# Why a wrapper and not healthcheck.sh directly in cron: a monitor running ON
# the monitored machine can never report that the machine died. Here we invert
# the signal — every successful run pings an external service
# (healthchecks.io). If the pings STOP, for any reason (failed check, dead cron,
# full disk, vanished instance), the external service alerts. Silence itself
# becomes the alarm.
#
# Installation: see monitoring.md.
#
# Variables (from /etc/bcss-monitoring.env):
#   BASE_URL          — the public root (e.g. https://app.bcss.ro)
#   HC_PING_URL       — the healthchecks.io ping URL for THIS check
#   BACKUP_DIR        — optional; checks the age and size of the last backup
#   BACKUP_MAX_AGE_H  — default 26
#   LOG_FILE          — default /var/log/bcss-healthcheck.log
set -uo pipefail

# Read the configuration ourselves so the cron line stays SHORT. Doing
# `set -a; . /etc/…; set +a;` inline in the crontab pushed the entry past 130
# characters, and a crontab entry must fit on a SINGLE physical line — pasted
# into an editor it split in two and cron rejected it with "bad minute".
ENV_FILE="${BCSS_MONITORING_ENV:-/etc/bcss-monitoring.env}"
if [ -r "${ENV_FILE}" ]; then set -a; . "${ENV_FILE}"; set +a; fi

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOG_FILE="${LOG_FILE:-/var/log/bcss-healthcheck.log}"
TS="$(date -u +%FT%TZ)"

ping_hc() { # path (empty = success), body
  [ -n "${HC_PING_URL:-}" ] || return 0
  curl -fsS -m 10 --retry 3 --data-raw "${2:-}" "${HC_PING_URL}${1:-}" >/dev/null 2>&1 || true
}

# Signal the start: healthchecks.io can then also alert if a run HANGS (starts
# and never finishes), not only if it disappears entirely.
ping_hc "/start" ""

OUTPUT="$(BASE_URL="${BASE_URL:-}" \
          BACKUP_DIR="${BACKUP_DIR:-}" \
          BACKUP_MAX_AGE_H="${BACKUP_MAX_AGE_H:-26}" \
          bash "${HERE}/healthcheck.sh" 2>&1)"
RC=$?

printf '%s [rc=%s]\n%s\n' "${TS}" "${RC}" "${OUTPUT}" >> "${LOG_FILE}" 2>/dev/null

# Also to stdout: cron redirects it to /dev/null anyway, but on a manual run —
# exactly what the install instructions ask for — a completely silent script
# gives no way to tell a pass from a script that never ran.
printf '%s\n' "${OUTPUT}"

if [ "${RC}" -eq 0 ]; then
  ping_hc "" "${OUTPUT}"
else
  # Send the full output as the body — the alert then contains the reason, not
  # just "something failed", so nobody has to SSH in to find out what.
  ping_hc "/fail" "${OUTPUT}"
fi

exit "${RC}"
