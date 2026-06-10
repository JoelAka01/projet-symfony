#!/usr/bin/env sh
set -e

export COMPOSER_CACHE_DIR="${COMPOSER_CACHE_DIR:-/tmp/composer}"

mkdir -p "${APP_CACHE_DIR:-/tmp/symfony-cache}" "${APP_LOG_DIR:-/tmp/symfony-log}" var/cache var/log

lock_hash="$(sha256sum composer.lock | awk '{ print $1 }')"
installed_hash="$(cat vendor/.composer.lock.sha256 2>/dev/null || true)"

if [ ! -f vendor/autoload.php ] || [ "$lock_hash" != "$installed_hash" ]; then
    composer install --no-interaction --prefer-dist --no-progress --optimize-autoloader --no-scripts
    printf '%s' "$lock_hash" > vendor/.composer.lock.sha256
fi

php bin/console cache:warmup --no-interaction

exec php bin/console messenger:consume async --time-limit=3600 --memory-limit=256M -vv
