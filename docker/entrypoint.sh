#!/bin/sh
set -e

# Clear build-time config cache (which defaults to SQLite since env vars aren't available during build)
php artisan config:clear 2>/dev/null || true
php artisan cache:clear 2>/dev/null || true

# Cache configs with the actual runtime environment variables
php artisan config:cache || true
php artisan view:cache || true
php artisan event:cache || true

# Ensure DB is migrated and seeded with popular games on first boot
php artisan migrate --force || true
php artisan games:seed-popular || true

# Populate game details and prices in background so container starts immediately
(php artisan steam:update-games --limit=50 && php artisan prices:scrape-all) &

# Start Supervisor (manages php-fpm + nginx + queue worker)
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
