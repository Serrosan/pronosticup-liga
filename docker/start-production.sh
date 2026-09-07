#!/bin/bash
set -e

php artisan storage:link || true
php artisan config:cache
php artisan route:cache
php artisan view:cache || true
php artisan migrate --force

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf