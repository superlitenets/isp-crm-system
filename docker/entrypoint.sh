#!/bin/bash
set -e

cd /var/www/html

if [ ! -d "vendor" ] || [ ! -f "vendor/autoload.php" ]; then
    echo "Installing Composer dependencies..."
    composer install --no-dev --optimize-autoloader
fi

chown -R www-data:www-data /var/www/html/vendor 2>/dev/null || true

exec php-fpm
