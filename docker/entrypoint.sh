#!/bin/sh
set -e

# migrate, never migrate:fresh — this container may be starting against a
# self-hoster's real data. Caching config/routes/views is safe every time:
# Laravel rebuilds them from what's already on disk, nothing destructive.
php artisan migrate --force
php artisan storage:link --force 2>/dev/null || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Everything above runs as root; php-fpm's workers run as www-data. A file
# any of those commands touched for the first time — storage/logs/laravel.log
# on a startup warning, a fresh view cache entry — would otherwise be
# root-owned, and every later www-data write to it fails.
chown -R www-data:www-data storage bootstrap/cache

exec "$@"
