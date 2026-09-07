#!/bin/bash
set -e

echo "Esperando a que MySQL esté listo en ${DB_HOST}:${DB_PORT}..."
until (echo > /dev/tcp/${DB_HOST}/${DB_PORT}) 2>/dev/null; do
    echo "MySQL aún no responde, reintentando en 2 segundos..."
    sleep 2
done
echo "MySQL está listo."

php artisan storage:link || true
php artisan config:cache
php artisan route:cache
php artisan view:cache || true
php artisan migrate --force

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf