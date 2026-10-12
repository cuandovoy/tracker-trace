#!/bin/sh
set -eu
if [ -z "${APP_KEY:-}" ]; then
    echo 'APP_KEY es obligatoria. Generala una vez y guardala en Dokploy.' >&2
    exit 1
fi
php artisan package:discover --no-interaction
php artisan config:cache
php artisan route:cache
php artisan view:cache
exec "$@"
