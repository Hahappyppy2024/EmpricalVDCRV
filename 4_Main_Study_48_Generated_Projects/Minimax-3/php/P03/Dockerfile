FROM php:8.3-cli-alpine AS base

ENV APP_ENV=local \
    APP_DEBUG=true \
    COMPOSER_ALLOW_SUPERUSER=1

RUN apk add --no-cache git unzip icu-dev libzip-dev oniguruma-dev $PHPIZE_DEPS \
    && docker-php-ext-install pdo pdo_sqlite zip intl mbstring \
    && apk del --no-cache $PHPIZE_DEPS

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-progress --no-scripts --no-autoloader

COPY . .
RUN composer dump-autoload --optimize --no-interaction

RUN mkdir -p data && chmod -R 0775 data

EXPOSE 8080

CMD ["sh", "-c", "php bin/seed.php && php -S 0.0.0.0:8080 -t public public/index.php"]