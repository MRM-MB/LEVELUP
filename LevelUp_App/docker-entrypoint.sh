#!/bin/bash
set -e

# Setup SQLite database if using sqlite
if [ "$DB_CONNECTION" = "sqlite" ]; then
    echo "Using SQLite database..."
    if [ ! -f /var/www/html/database/database.sqlite ]; then
        echo "Creating database.sqlite..."
        touch /var/www/html/database/database.sqlite
    fi
    chown www-data:www-data /var/www/html/database/database.sqlite
fi

# Run migrations (force is needed in production)
echo "Running migrations..."
php artisan migrate --force

# Optimize caches
echo "Caching config..."
php artisan config:cache
echo "Caching routes..."
php artisan route:cache
echo "Caching views..."
php artisan view:cache

# Start Apache in foreground
echo "Starting Apache..."
apache2-foreground
