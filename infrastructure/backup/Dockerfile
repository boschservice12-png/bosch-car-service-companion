# Backup scheduler image: PostgreSQL client (pg_dump) + MinIO client (mc).
#
# The `mc` binary is copied from the official minio/mc image (no runtime
# download). Pinned version: `:latest` is the same trap as the php:8-fpm drift
# documented in docs/TROUBLESHOOTING.md. `mc` performs the off-box copy of the
# backups, so a silent version change would land squarely on the backup path.
# The same version appears in compose.prod.yaml's `minio-setup` service —
# update them together.
FROM minio/mc:RELEASE.2025-08-13T08-35-41Z AS mc

FROM postgres:16

# The postgres:16 image ships NO CA certificates (`ca-certificates` is not
# installed and /etc/ssl/certs/ca-certificates.crt does not exist). Without them
# `mc` rejects every HTTPS endpoint:
#   x509: certificate signed by unknown authority
# This is invisible against a plain-HTTP MinIO and only appears against a real
# TLS off-box store (Lightsail object storage, AWS S3).
RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates \
    && rm -rf /var/lib/apt/lists/*

COPY --from=mc /usr/bin/mc /usr/local/bin/mc
COPY backup-cron.sh /usr/local/bin/backup-cron.sh
# restore.sh belongs HERE, next to the backup: the drill (and a real recovery)
# must be runnable in the same container that produced the backup — the same
# pg_dump/psql and mc versions, with no separate tooling.
#   docker compose … run --rm --entrypoint restore.sh backup /backups/<ts>
COPY restore.sh /usr/local/bin/restore.sh
COPY backup.sh /usr/local/bin/backup.sh
# The disaster-scenario tool: brings a backup back from the bucket when the
# instance itself (and with it the `backups` volume) has been lost.
COPY fetch-offsite.sh /usr/local/bin/fetch-offsite.sh
RUN chmod +x /usr/local/bin/backup-cron.sh /usr/local/bin/restore.sh \
             /usr/local/bin/backup.sh /usr/local/bin/fetch-offsite.sh
ENTRYPOINT ["/usr/local/bin/backup-cron.sh"]
