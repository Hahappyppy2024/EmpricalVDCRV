# syntax=docker/dockerfile:1
FROM php:8.3-cli-bookworm

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1 \
    APP_ENV=production \
    APP_DEBUG=false

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git unzip libsqlite3-0 libsqlite3-dev ca-certificates \
    && docker-php-ext-install pdo pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY composer.json composer.lock* ./
RUN if [ -f composer.lock ]; then \
        curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
        && composer install --no-dev --optimize-autoloader --no-interaction --no-scripts \
        && rm -f /usr/local/bin/composer; \
    else \
        curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer; \
    fi

COPY . .

RUN mkdir -p storage/uploads storage/exports \
    && php bin/init-db.php \
    && chown -R www-data:www-data storage || true

EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080", "-t", "public", "public/index.php"]
