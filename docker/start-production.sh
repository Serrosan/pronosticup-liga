#!/bin/bash
set -e

echo "Esperando a que MySQL esté listo en ${DB_HOST}:${DB_PORT}..."
until php -r "
try {
    new PDO('mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'));
    exit(0);
} catch (Exception \$e) {
    exit(1);
}
" 2>/dev/null; do
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