# Backup ütemező kép: PostgreSQL kliens (pg_dump) + MinIO kliens (mc).
# A `mc` binárist a hivatalos minio/mc képből másoljuk (nincs runtime letöltés).
FROM minio/mc:latest AS mc

FROM postgres:16
COPY --from=mc /usr/bin/mc /usr/local/bin/mc
COPY backup-cron.sh /usr/local/bin/backup-cron.sh
# A restore.sh-nak ITT a helye, a mentés MELLETT: a drillt (és egy éles
# visszaállítást) ugyanabban a konténerben kell tudni futtatni, ami a mentést
# készítette — ugyanaz a pg_dump/psql és mc verzió, külön eszközök nélkül.
#   docker compose … run --rm --entrypoint restore.sh backup /backups/<ts>
COPY restore.sh /usr/local/bin/restore.sh
COPY backup.sh /usr/local/bin/backup.sh
# A katasztrófa-forgatókönyv eszköze: ezzel lehet visszahozni egy mentést a
# bucketből, ha maga az instance (és vele a `backups` volume) elveszett.
COPY fetch-offsite.sh /usr/local/bin/fetch-offsite.sh
RUN chmod +x /usr/local/bin/backup-cron.sh /usr/local/bin/restore.sh \
             /usr/local/bin/backup.sh /usr/local/bin/fetch-offsite.sh
ENTRYPOINT ["/usr/local/bin/backup-cron.sh"]
