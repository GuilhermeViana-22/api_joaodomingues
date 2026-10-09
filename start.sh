#!/bin/bash
set -e

chmod -R 775 storage bootstrap/cache || true
php artisan storage:link || true

if [ -z "${APP_KEY}" ]; then
    echo "APP_KEY em falta. Defina a chave no ambiente antes de publicar."
    exit 1
fi

php artisan migrate --force --no-interaction
php artisan passport:preparar --no-interaction

# Base vazia (primeiro deploy): textos e definições do site. Depois disso
# não volta a correr, para não apagar o que foi editado no painel.
if [ "$(php artisan tinker --execute='echo \App\Models\TextoSite::count();' 2>/dev/null | tail -n1)" = "0" ]; then
    php artisan db:seed --class=TextosSiteSeeder --force --no-interaction
    php artisan db:seed --class=DefinicoesSiteSeeder --force --no-interaction
fi
php artisan db:seed --class=UtilizadoresSeeder --force --no-interaction

(
    while true; do
        php artisan queue:work --sleep=3 --tries=3 --max-time=3600
        sleep 3
    done
) &

exec php artisan serve --host=0.0.0.0 --port=8048
