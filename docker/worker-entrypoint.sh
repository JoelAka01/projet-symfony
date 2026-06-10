#!/usr/bin/env sh
set -e

export COMPOSER_CACHE_DIR="${COMPOSER_CACHE_DIR:-/tmp/composer}"

mkdir -p var/cache var/log

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist --no-scripts
fi

exec php bin/console messenger:consume async --time-limit=3600 --memory-limit=256M -vv
