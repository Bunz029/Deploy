#!/bin/bash

# Railway deployment script for Laravel
echo "Starting Laravel deployment..."

# Generate application key if not exists
if [ -z "$APP_KEY" ]; then
    php artisan key:generate --force
fi

# Set default database connection to mysql for Railway
if [ -z "$DB_CONNECTION" ]; then
    export DB_CONNECTION=mysql
fi

# Run migrations
php artisan migrate --force

# Create storage directories and set permissions
mkdir -p storage/app/public/maps
mkdir -p storage/app/public/buildings
mkdir -p storage/app/public/rooms
chmod -R 775 storage/app/public

# Create storage link for file uploads
php artisan storage:link

# Set proper ownership (if possible)
chown -R www-data:www-data storage/app/public 2>/dev/null || true

# Clear and cache configuration
php artisan config:clear
php artisan config:cache

# Clear and cache routes
php artisan route:clear
php artisan route:cache

# Clear and cache views
php artisan view:clear
php artisan view:cache

# Optimize for production
php artisan optimize

echo "Laravel deployment completed!"
