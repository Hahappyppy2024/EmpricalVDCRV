FROM php:8.3-cli-alpine

RUN apk add --no-cache \
        git \
        unzip \
    && docker-php-ext-install pdo_sqlite

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-progress --no-scripts \
    && composer dump-autoload --no-dev --classmap-authoritative

COPY . .

RUN chmod -R 777 data storage

EXPOSE 8080 8081

CMD ["php", "-S", "0.0.0.0:8080", "-t", "/app/public", "/app/public/router.php"]
