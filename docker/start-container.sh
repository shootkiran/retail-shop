#!/bin/sh
set -e

# Ensure SQLite database file exists with correct permissions
if [ ! -f database/database.sqlite ]; then
    touch database/database.sqlite
    chown www-data:www-data database/database.sqlite
    chmod 664 database/database.sqlite
fi

php artisan migrate --seed --force

php-fpm -D
exec nginx -g "daemon off;"
