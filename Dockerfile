# ============================================================
# Stage 1 — Builder: instala dependências PHP via Composer
# ============================================================
FROM php:8.5-fpm-bookworm AS builder

RUN apt-get update && apt-get install -y --no-install-recommends \
    git curl unzip zip \
    libpng-dev libonig-dev libxml2-dev libpq-dev libzip-dev libicu-dev \
    && docker-php-ext-install \
        pdo pdo_pgsql pgsql mbstring exif pcntl bcmath gd zip intl xml \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
COPY --chown=www-data:www-data . .

RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# ============================================================
# Stage 2 — Runtime: PHP-FPM
# ============================================================
FROM php:8.5-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev libonig-dev libxml2-dev libpq-dev libzip-dev libicu-dev \
    supervisor vim less \
    && docker-php-ext-install \
        pdo pdo_pgsql pgsql mbstring exif pcntl bcmath gd zip intl xml \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
COPY --from=builder --chown=www-data:www-data /var/www .

RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache

COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/php/zz-docker.conf /usr/local/etc/php-fpm.d/zz-docker.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY docker/worker/supervisord.conf /etc/supervisor/conf.d/queue.conf
COPY docker/worker/entrypoint.sh /usr/local/bin/entrypoint-worker.sh
RUN chmod +x /usr/local/bin/entrypoint.sh /usr/local/bin/entrypoint-worker.sh

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
