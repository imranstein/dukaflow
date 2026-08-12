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

exec "$@"
