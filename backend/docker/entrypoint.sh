#!/bin/sh
set -e

# Render inyecta el puerto por entorno; Apache debe escuchar ahi.
PORT="${PORT:-10000}"
sed -ri "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

php artisan migrate --force --no-interaction
php artisan db:seed --force --no-interaction

php artisan config:cache
php artisan route:cache

exec apache2-foreground
