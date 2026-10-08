FROM php:8.3-cli-alpine

RUN apk add --no-cache \
        sqlite \
        libzip-dev \
    && docker-php-ext-install pdo_sqlite \
    && docker-php-ext-enable pdo_sqlite

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --optimize-autoloader

COPY . .

RUN mkdir -p storage/manuscripts storage/exports storage/sessions \
    && chmod +x bin/reset_db.php bin/serve.php bin/websocket.php docker/entrypoint.sh

EXPOSE 8080 8090

ENTRYPOINT ["/bin/sh", "/app/docker/entrypoint.sh"]
