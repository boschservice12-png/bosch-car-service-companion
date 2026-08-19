# Demo backend image: PHP CLI + extensions + Composer, served by the built-in
# server on :8080. At start-up the entrypoint runs migrations and the demo seed.
# This is the DEMO image; production uses infrastructure/docker/backend.Dockerfile.
FROM php:8.3-cli-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libpq-dev libicu-dev libzip-dev \
    && docker-php-ext-install pdo pdo_pgsql intl zip opcache \
    && rm -rf /var/lib/apt/lists/*

# PHP limits for the ASM Excel imports (.xls files of several MB).
RUN { echo "upload_max_filesize=12M"; echo "post_max_size=16M"; echo "memory_limit=512M"; echo "max_execution_time=600"; } > /usr/local/etc/php/conf.d/uploads.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /app

# Install dependencies first (better layer caching), then copy the source.
COPY backend/composer.json backend/composer.lock* ./
RUN composer install --no-interaction --no-scripts --prefer-dist --no-progress

COPY backend/ /app/
RUN composer dump-autoload --optimize --no-interaction

COPY demo/backend-entrypoint.sh /usr/local/bin/entrypoint.sh
COPY demo/worker-entrypoint.sh /usr/local/bin/worker-entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh /usr/local/bin/worker-entrypoint.sh

EXPOSE 8080
CMD ["/usr/local/bin/entrypoint.sh"]
