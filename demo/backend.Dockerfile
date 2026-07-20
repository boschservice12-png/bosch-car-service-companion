# Imagine backend pentru demo: PHP CLI + extensii + Composer, servind cu serverul
# built-in pe :8080. La pornire (entrypoint) rulează migrațiile și seed-ul demo.
FROM php:8.3-cli-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libpq-dev libicu-dev libzip-dev \
    && docker-php-ext-install pdo pdo_pgsql intl zip opcache \
    && rm -rf /var/lib/apt/lists/*

# Limite PHP pentru importurile Excel din ASM (fișiere .xls de mai mulți MB).
RUN { echo "upload_max_filesize=12M"; echo "post_max_size=16M"; echo "memory_limit=512M"; echo "max_execution_time=600"; } > /usr/local/etc/php/conf.d/uploads.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /app

# Instalăm întâi dependențele (cache mai bun), apoi copiem sursa.
COPY backend/composer.json backend/composer.lock* ./
RUN composer install --no-interaction --no-scripts --prefer-dist --no-progress

COPY backend/ /app/
RUN composer dump-autoload --optimize --no-interaction

COPY demo/backend-entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8080
CMD ["/usr/local/bin/entrypoint.sh"]
