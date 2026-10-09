FROM composer:2.8 AS composer
WORKDIR /app
COPY composer.json ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts

FROM php:8.3-cli-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends \
    sqlite3 \
    libsqlite3-dev \
    unzip \
    git \
 && docker-php-ext-install pdo_sqlite \
 && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY --from=composer /app/vendor ./vendor
COPY . .
RUN mkdir -p var/storage var/mail var/db
RUN cp .env.example .env
EXPOSE 8080
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public", "public/router.php"]