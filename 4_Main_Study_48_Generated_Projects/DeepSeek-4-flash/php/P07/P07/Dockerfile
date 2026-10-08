FROM php:8.3-cli-alpine

RUN apk add --no-cache git unzip sqlite \
    && docker-php-ext-install pdo pdo_sqlite pcntl posix

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-progress --no-scripts

COPY . .

RUN mkdir -p storage/db storage/files var \
    && chmod -R 0777 storage var

EXPOSE 8080 8282

# Start both the web server and the Workerman real-time server.
CMD ["sh", "-c", "php -S 0.0.0.0:8080 -t public public/index.php & php bin/websocket.php start"]
