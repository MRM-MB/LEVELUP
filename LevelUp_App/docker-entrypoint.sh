#!/bin/bash
set -e

# Generate APP_KEY if it's missing or invalid (Render's generateValue isn't always Laravel compatible)
if [ -z "$APP_KEY" ] || [[ "$APP_KEY" != "base64:"* ]]; then
    echo "APP_KEY is missing or invalid. Generating a new one..."
    export APP_KEY=$(php artisan key:generate --show)
fi

# Ensure APP_URL/ASSET_URL match the Render service URL if provided
if [ -n "$RENDER_EXTERNAL_URL" ]; then
    export APP_URL="$RENDER_EXTERNAL_URL"
    export ASSET_URL="$RENDER_EXTERNAL_URL"
fi

# Setup SQLite database if using sqlite
if [ "$DB_CONNECTION" = "sqlite" ]; then
    echo "Using SQLite database..."
    DB_PATH="${DB_DATABASE:-/var/www/html/database/database.sqlite}"
    DB_DIR=$(dirname "$DB_PATH")
    if [ ! -d "$DB_DIR" ]; then
        mkdir -p "$DB_DIR"
    fi
    if [ ! -f "$DB_PATH" ]; then
        echo "Creating database.sqlite..."
        touch "$DB_PATH"
    fi
fi

# Run migrations and seed default data (force is needed in production)
echo "Running migrations and seeders..."
php artisan migrate --force --seed

# Optimize caches
echo "Caching config..."
php artisan config:cache
echo "Caching routes..."
php artisan route:cache
echo "Caching views..."
php artisan view:cache

# Fix permissions again after migrations and cache creation
# This ensures www-data owns everything created by root during startup
echo "Fixing permissions..."
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

if [ "$DB_CONNECTION" = "sqlite" ]; then
    DB_PATH="${DB_DATABASE:-/var/www/html/database/database.sqlite}"
    DB_DIR=$(dirname "$DB_PATH")
    chown -R www-data:www-data "$DB_DIR"

    # Ensure directory and file are writable for the web server
    chmod -R 777 "$DB_DIR"
    chmod 666 "$DB_PATH"
fi

# Start Apache in foreground
echo "Starting Apache..."
apache2-foreground
