#!/bin/sh
set -e

# Support Render dynamic $PORT (Render sets $PORT, e.g. 10000)
if [ -n "$PORT" ]; then
    echo "Configuring Apache to listen on port: $PORT"
    sed -i "s/80/$PORT/g" /etc/apache2/ports.conf
    sed -i "s/<VirtualHost \*:80>/<VirtualHost \*:$PORT>/g" /etc/apache2/sites-available/000-default.conf
fi

# Ensure storage directories exist and have correct permissions
mkdir -p /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Create storage symlink if needed
php artisan storage:link --force || true

# Optimize package discovery
if [ -n "$APP_KEY" ]; then
    php artisan package:discover --ansi || true
fi

# Run database migrations
echo "Running database migrations..."
php artisan migrate --force

# Start Apache in foreground
echo "Starting Apache web server..."
exec apache2-foreground
