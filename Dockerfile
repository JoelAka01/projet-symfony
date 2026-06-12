FROM php:8.3-cli-alpine

RUN apk add --no-cache \
        ca-certificates \
        curl-dev \
        git \
        icu-dev \
        libzip-dev \
        libxml2-dev \
        oniguruma-dev \
        postgresql-dev \
        unzip \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && curl -fsSL https://github.com/krakjoe/pcov/archive/refs/tags/v1.0.11.tar.gz -o /tmp/pcov.tar.gz \
    && tar -xf /tmp/pcov.tar.gz -C /tmp \
    && cd /tmp/pcov-1.0.11 \
    && phpize \
    && ./configure \
    && make -j$(nproc) \
    && make install \
    && docker-php-ext-enable pcov \
    && rm -rf /tmp/pcov* \
    && apk del .build-deps \
    && docker-php-ext-install \
        curl \
        dom \
        intl \
        mbstring \
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
