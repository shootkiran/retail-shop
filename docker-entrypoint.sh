#!/bin/bash
set -e

# Wait for dependencies (e.g. DB)
echo "Waiting for database connection..."
sleep 5

# Run migrations and cache optimizations
php artisan migrate --seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Start supervisord
exec "$@"
