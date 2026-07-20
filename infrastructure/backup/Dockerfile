# Backup ütemező kép: PostgreSQL kliens (pg_dump) + MinIO kliens (mc).
# A `mc` binárist a hivatalos minio/mc képből másoljuk (nincs runtime letöltés).
FROM minio/mc:latest AS mc

FROM postgres:16
COPY --from=mc /usr/bin/mc /usr/local/bin/mc
COPY backup-cron.sh /usr/local/bin/backup-cron.sh
RUN chmod +x /usr/local/bin/backup-cron.sh
ENTRYPOINT ["/usr/local/bin/backup-cron.sh"]
