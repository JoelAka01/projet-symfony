#!/usr/bin/env sh
set -e

export COMPOSER_CACHE_DIR="${COMPOSER_CACHE_DIR:-/tmp/composer}"

mkdir -p var/cache var/log

composer install --no-interaction --prefer-dist

exec php -S 0.0.0.0:8000 -t public public/index.php
