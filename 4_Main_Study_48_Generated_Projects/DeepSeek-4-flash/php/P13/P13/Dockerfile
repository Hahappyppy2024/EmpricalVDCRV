FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-dev --ignore-platform-reqs

FROM php:8.3-cli-alpine

RUN docker-php-ext-install pdo pdo_sqlite \
    && apk add --no-cache sqlite

WORKDIR /app
COPY --from=vendor /app/vendor /app/vendor
COPY . /app

# Ensure writable data + storage directories exist
RUN mkdir -p /app/data /app/storage/uploads

EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080", "-t", "public", "public/router.php"]
