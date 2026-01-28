#!/bin/bash
set -e

# Generate APP_KEY if it's missing or invalid (Render's generateValue isn't always Laravel compatible)
if [ -z "$APP_KEY" ] || [[ "$APP_KEY" != "base64:"* ]]; then
    echo "APP_KEY is missing or invalid. Generating a new one..."
    export APP_KEY=$(php artisan key:generate --show)
fi

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
