# Backend image (PHP-FPM) — the Symfony modular monolith.
#
# The PHP line is pinned deliberately. An unpinned `php:8-fpm` floated to PHP
# 8.5, where `pecl install redis` compiles but installs no module, breaking the
# backend build entirely. See docs/TROUBLESHOOTING.md.
FROM php:8.3-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libpq-dev libicu-dev libzip-dev \
    && docker-php-ext-install pdo pdo_pgsql intl zip opcache \
    && pecl install redis && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

# PHP limits for the ASM Excel imports (.xls files of several MB).
RUN { echo "upload_max_filesize=12M"; echo "post_max_size=16M"; echo "memory_limit=512M"; echo "max_execution_time=600"; } > /usr/local/etc/php/conf.d/uploads.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/app
COPY composer.json composer.lock* ./
RUN composer install --no-interaction --no-scripts --prefer-dist
COPY . .

EXPOSE 9000
CMD ["php-fpm"]
