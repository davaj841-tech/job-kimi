#!/bin/bash
set -e

echo "=== JobAzmoon Deployment ==="

# Pull code
git pull origin main

# Install dependencies
composer install --no-dev --optimize-autoloader
npm ci
npm run build

# Database
php artisan migrate --force
php artisan db:seed --force

# Cache
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan optimize

# Permissions
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod 640 .env

# Restart queue
php artisan queue:restart

echo "=== Deployment Complete ==="
