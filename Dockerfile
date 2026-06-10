FROM php:8.3-cli-alpine

RUN apk add --no-cache \
        ca-certificates \
        curl-dev \
        git \
        icu-dev \
        libzip-dev \
        postgresql-dev \
        unzip \
    && docker-php-ext-install \
        curl \
        intl \
        opcache \
        pdo \
        pdo_pgsql \
        zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock symfony.lock ./

RUN composer install --no-interaction --prefer-dist --no-progress --optimize-autoloader --no-scripts \
    && sha256sum composer.lock | awk '{ print $1 }' > vendor/.composer.lock.sha256

COPY docker/php/conf.d/app.ini /usr/local/etc/php/conf.d/app.ini
