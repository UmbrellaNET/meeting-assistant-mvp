FROM composer:2 AS build
WORKDIR /app
COPY composer.json ./
RUN composer install --no-interaction --prefer-dist --no-progress --no-scripts
COPY . .
RUN composer dump-autoload --optimize --no-interaction

FROM php:8.4-cli-alpine
RUN apk add --no-cache postgresql-dev libzip-dev curl-dev curl bash oniguruma-dev icu-dev libxml2-dev \
    && docker-php-ext-install pdo_pgsql pcntl mbstring bcmath intl opcache zip curl dom xml \
    && rm -rf /tmp/*
WORKDIR /var/www/html
COPY --from=build /app /var/www/html
COPY docker/php.ini /usr/local/etc/php/conf.d/meeting-intelligence.ini
RUN mkdir -p storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache
EXPOSE 8000
CMD ["sh", "-c", "php artisan migrate --force && php artisan db:seed --force && (while true; do php artisan queue:work --queue=meetings,default --sleep=3 --tries=3 --timeout=600; sleep 2; done &) && php artisan serve --host=0.0.0.0 --port=${PORT:-10000}"]
