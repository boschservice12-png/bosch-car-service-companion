# Backup ütemező kép: PostgreSQL kliens (pg_dump) + MinIO kliens (mc).
# A `mc` binárist a hivatalos minio/mc képből másoljuk (nincs runtime letöltés).
# Rögzített verzió: a `:latest` ugyanaz a csapda, mint a php:8-fpm elsodródása
# (lásd CLAUDE.md 7. szakasz). Az `mc` végzi a mentések off-box másolását, tehát
# egy néma verzióváltás pont a mentési útvonalat érintené. Ugyanez a verzió
# szerepel a compose.prod.yaml `minio-setup` szolgáltatásánál — együtt frissítsd.
FROM minio/mc:RELEASE.2025-08-13T08-35-41Z AS mc

FROM postgres:16

# A postgres:16 image NEM tartalmaz CA-tanúsítványokat (`ca-certificates` nincs
# telepítve, /etc/ssl/certs/ca-certificates.crt nem létezik). Emiatt az `mc`
# minden HTTPS-végpontot elutasít:
#   x509: certificate signed by unknown authority
# Sima HTTP-s MinIO ellen ez nem látszik — csak valódi, TLS-es off-box tár
# (Lightsail object storage, AWS S3) ellen jön elő.
RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates \
    && rm -rf /var/lib/apt/lists/*

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
