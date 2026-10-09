#!/bin/bash
set -e

chmod -R 775 storage bootstrap/cache || true
php artisan storage:link || true

if [ -z "${APP_KEY}" ]; then
    echo "APP_KEY em falta. Defina a chave no ambiente antes de publicar."
    exit 1
fi

php artisan migrate --force --no-interaction

(
    while true; do
        php artisan queue:work --sleep=3 --tries=3 --max-time=3600
        sleep 3
    done
) &

exec php artisan serve --host=0.0.0.0 --port=8048
