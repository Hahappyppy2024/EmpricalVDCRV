# P01 Learning Management System - PHP 8.3 image
FROM php:8.3-cli

# System dependencies for Composer and SQLite
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip zip libzip-dev sqlite3 \
    && docker-php-ext-install pdo pdo_sqlite zip \
    && rm -rf /var/lib/apt/lists/*

# Install Composer (pinned)
COPY --from=composer:2.8.4 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/lms

# Install dependencies first for better layer caching
COPY composer.json composer.lock* ./
RUN composer install --no-interaction --no-progress --no-dev --prefer-dist --optimize-autoloader || composer install --no-interaction --no-progress --no-dev --prefer-dist --optimize-autoloader

# Copy application source
COPY . .

RUN mkdir -p data data/uploads data/exports \
    && chmod -R 777 data \
    && cp -n .env.example .env || true

# Initialize the database with deterministic fixtures
RUN php bin/reset.php

EXPOSE 8080 8280

COPY bin/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

ENTRYPOINT ["/usr/local/bin/entrypoint"]
