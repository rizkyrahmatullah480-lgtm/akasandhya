#!/bin/sh
set -e

# APP_KEY wajib ada; generate sementara bila env platform belum mengisinya.
if [ -z "$APP_KEY" ]; then
    export APP_KEY="base64:$(head -c 32 /dev/urandom | base64)"
fi

php artisan package:discover --ansi
php artisan storage:link --force 2>/dev/null || true
php artisan config:cache
php artisan route:cache

# Jalankan migrasi hanya bila diminta (set RUN_MIGRATIONS=true di env platform).
if [ "$RUN_MIGRATIONS" = "true" ]; then
    php artisan migrate --force
fi

apache2-foreground
