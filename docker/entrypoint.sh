#!/bin/bash
set -e

echo "==> Aguardando banco de dados..."
until php artisan db:monitor 2>/dev/null; do
    sleep 2
done

echo "==> Gerando APP_KEY (se necessário)..."
if [ -z "$APP_KEY" ]; then
    php artisan key:generate --force
fi

echo "==> Executando migrations..."
php artisan migrate --force

echo "==> Corrigindo permissões de storage..."
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
chmod -R 775 /var/www/storage /var/www/bootstrap/cache

echo "==> Otimizando configurações..."
php artisan config:clear
php artisan config:cache
php artisan route:clear
php artisan route:cache
php artisan view:clear
php artisan view:cache

echo "==> Iniciando PHP-FPM..."
exec php-fpm -F
