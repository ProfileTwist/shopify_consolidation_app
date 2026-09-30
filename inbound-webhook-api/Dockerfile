# ---- PHP 8.4 FPM base for the webhook API ----
FROM php:8.4-fpm-alpine

RUN apk add --no-cache bash git icu-dev libzip-dev oniguruma-dev \
    && docker-php-ext-install bcmath intl zip opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --prefer-dist --no-dev --optimize-autoloader || true

COPY . .
RUN composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
