#!/usr/bin/env bash
set -euo pipefail

PORT="${PORT:-7860}"

# Update Apache port if platform injects a custom $PORT other than 7860
if [ "${PORT}" != "7860" ]; then
    sed -ri "s/Listen [0-9]+/Listen ${PORT}/g" /etc/apache2/ports.conf
    sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/g" /etc/apache2/sites-available/000-default.conf
fi

mkdir -p /var/www/html/storage/app/public/leaves \
    /var/www/html/storage/framework/cache/data \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/logs

if [ ! -L /var/www/html/public/storage ]; then
    php artisan storage:link || true
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force --no-interaction
fi

php artisan config:clear || true
php artisan route:cache || true
php artisan view:cache || true

exec apache2-foreground
