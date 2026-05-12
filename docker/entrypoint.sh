#!/bin/sh
set -e

# Clear build-time config cache (which defaults to SQLite since env vars aren't available during build)
php artisan config:clear 2>/dev/null || true
php artisan cache:clear 2>/dev/null || true

# Cache configs with the actual runtime environment variables
php artisan config:cache || true
php artisan view:cache || true
php artisan event:cache || true

# Ensure DB is migrated and seeded
php artisan migrate --force || true
php artisan db:seed --class=StoreSeeder --force || true
php artisan games:seed-popular || true

# Re-import real prices from JSON sources
php artisan prices:import-cheapshark-json || true
php artisan prices:import-eneba-json || true
php artisan gamivo:import-json || true
php artisan g2a:import-json || true
php artisan instantgaming:import-json || true
php artisan kinguin:import-json || true

# Seed vouchers
php artisan db:seed --class=VoucherSeeder --force || true

# Start Supervisor (manages php-fpm + nginx + queue worker + schedule runner)
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
