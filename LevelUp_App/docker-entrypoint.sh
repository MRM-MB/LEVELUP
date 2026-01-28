#!/bin/bash
set -e

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
