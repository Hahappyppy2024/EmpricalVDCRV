FROM composer:2.8 AS deps
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist
FROM php:8.3-cli
RUN docker-php-ext-install pdo_sqlite
WORKDIR /app
COPY --from=deps /app/vendor vendor
COPY . .
RUN php bin/reset-database.php
EXPOSE 8080
CMD ["php","-S","0.0.0.0:8080","-t","public","public/router.php"]
