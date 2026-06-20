#!/usr/bin/env sh
set -e

export COMPOSER_CACHE_DIR="${COMPOSER_CACHE_DIR:-/tmp/composer}"

mkdir -p "${APP_CACHE_DIR:-/tmp/symfony-cache}" "${APP_LOG_DIR:-/tmp/symfony-log}" var/cache var/log

if [ ! -f .env ] && [ -f .env.example ]; then
    cp .env.example .env
fi

lock_hash="$(sha256sum composer.lock | awk '{ print $1 }')"
attempt=0

while [ ! -f vendor/autoload.php ] || [ "$lock_hash" != "$(cat vendor/.composer.lock.sha256 2>/dev/null || true)" ]; do
    attempt=$((attempt + 1))

    if [ "$attempt" -gt 120 ]; then
        echo "Timed out waiting for the PHP container to install Composer dependencies." >&2
        exit 1
    fi

    sleep 1
done

php bin/console cache:warmup --no-interaction

exec php bin/console messenger:consume async --time-limit=3600 --memory-limit=256M -vv
