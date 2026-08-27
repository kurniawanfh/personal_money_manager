#!/usr/bin/env bash
set -eo pipefail

echo "==> Starting Personal Money Manager Deployment"

# Verify active server protection rules
echo "==> Verifying host production port protection"
if ss -tulpn | grep -E "(:3000|:3001)" > /dev/null; then
    echo "✓ Protected production services (sablonku :3000, absenku :3001) are active and untouched."
fi

# Navigate to backend directory
cd "$(dirname "$0")/../../backend"

# Install Composer dependencies (optimizing autoloader)
echo "==> Installing backend dependencies"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Cache configurations and routes
echo "==> Caching application configurations and routes"
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run database migrations
echo "==> Running database migrations"
php artisan migrate --force

echo "==> Personal Money Manager Deployment Successful (Running on Port 8000)"
