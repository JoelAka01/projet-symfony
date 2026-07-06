FROM php:8.3-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        ca-certificates \
        libcurl4-openssl-dev \
        git \
        libicu-dev \
        libzip-dev \
        libxml2-dev \
        libonig-dev \
        libpq-dev \
        unzip \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && docker-php-ext-install \
        curl \
        dom \
        intl \
        mbstring \
        opcache \
        pdo \
        pdo_pgsql \
        zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock symfony.lock ./

RUN composer install --no-interaction --prefer-dist --no-progress --optimize-autoloader --no-scripts \
    && sha256sum composer.lock | awk '{ print $1 }' > vendor/.composer.lock.sha256

COPY docker/php/conf.d/app.ini /usr/local/etc/php/conf.d/app.ini

COPY . .

EXPOSE 8000

CMD ["sh", "docker/entrypoint.sh"]
