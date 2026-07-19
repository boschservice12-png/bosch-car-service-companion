# Imagine backend (PHP-FPM) — modular monolith Symfony.
# Linia PHP se fixează în ADR-0004; folosim eticheta stabilă curentă.
FROM php:8-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libpq-dev libicu-dev libzip-dev \
    && docker-php-ext-install pdo pdo_pgsql intl zip opcache \
    && pecl install redis && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/app
COPY composer.json composer.lock* ./
RUN composer install --no-interaction --no-scripts --prefer-dist
COPY . .

EXPOSE 9000
CMD ["php-fpm"]
