# ---- PHP 8.4 FPM base for the Laravel API ----
FROM php:8.4-fpm-alpine

# System deps + PHP extensions Laravel + MySQL need.
RUN apk add --no-cache \
        bash \
        git \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
        mysql-client \
    && docker-php-ext-install pdo_mysql bcmath intl zip opcache

# Composer (copied from the official image).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install PHP dependencies first for better layer caching.
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --prefer-dist --no-dev --optimize-autoloader || true

# Copy the application.
COPY . .
RUN composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
