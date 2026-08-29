#!/bin/sh
set -e

php artisan migrate --force
php artisan db:seed --class=CategorySeeder --force

# No view:cache — this is a pure JSON API with no Blade views to cache.
php artisan config:cache
php artisan route:cache

exec php-fpm
