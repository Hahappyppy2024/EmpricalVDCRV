# PHP runtime image
FROM php:8.3-cli

# System dependencies
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip curl libzip-dev \
    && docker-php-ext-install pdo pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts

COPY . .

# Expose HTTP and WebSocket ports
EXPOSE 8080 9090

# Healthcheck against the app health endpoint
HEALTHCHECK --interval=15s --timeout=5s --retries=3 \
    CMD curl -fsS http://127.0.0.1:8080/api/monitor/health || exit 1

# Reset/seed the database then serve the app with the PHP built-in server
CMD ["sh", "-c", "php bin/reset-db.php && php -S 0.0.0.0:8080 -t public public/index.php"]
