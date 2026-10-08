FROM composer:2.8.10 AS dependencies
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

FROM php:8.3.23-cli-alpine3.22
RUN docker-php-ext-install pdo_sqlite
WORKDIR /app
COPY --from=dependencies /app/vendor ./vendor
COPY . .
RUN mkdir -p var/uploads && chown -R www-data:www-data var
USER www-data
EXPOSE 8080
CMD ["sh", "-c", "php bin/reset-database.php --if-missing && php -S 0.0.0.0:8080 -t public public/router.php"]
