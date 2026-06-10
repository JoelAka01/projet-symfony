#!/usr/bin/env sh
set -e

export COMPOSER_CACHE_DIR="${COMPOSER_CACHE_DIR:-/tmp/composer}"
export PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-4}"

mkdir -p "${APP_CACHE_DIR:-/tmp/symfony-cache}" "${APP_LOG_DIR:-/tmp/symfony-log}" public/assets var/cache var/log

lock_hash="$(sha256sum composer.lock | awk '{ print $1 }')"
installed_hash="$(cat vendor/.composer.lock.sha256 2>/dev/null || true)"

if [ ! -f vendor/autoload.php ] || [ "$lock_hash" != "$installed_hash" ]; then
    composer install --no-interaction --prefer-dist --no-progress --optimize-autoloader --no-scripts
    printf '%s' "$lock_hash" > vendor/.composer.lock.sha256
fi

php bin/console cache:warmup --no-interaction
php bin/console asset-map:compile --no-interaction

exec php -S 0.0.0.0:8000 -t public docker/router.php
