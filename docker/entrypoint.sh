#!/bin/sh
set -e

cd /var/www/html

mkdir -p \
    bootstrap/cache \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs

if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
    DB_PATH="${DB_DATABASE:-/var/www/html/database/database.sqlite}"
    mkdir -p "$(dirname "$DB_PATH")"
    touch "$DB_PATH"
fi

chown -R www-data:www-data storage bootstrap/cache database 2>/dev/null || true

if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY is not set. Generate one with: php artisan key:generate --show"
fi

php artisan storage:link --force >/dev/null 2>&1 || true

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

exec "$@"
