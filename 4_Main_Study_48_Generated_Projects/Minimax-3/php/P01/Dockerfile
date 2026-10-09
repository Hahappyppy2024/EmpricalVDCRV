FROM php:8.3-cli AS base

# System deps + PHP extensions required by the LMS
RUN apt-get update \
 && apt-get install -y --no-install-recommends git unzip libsqlite3-dev \
 && docker-php-ext-install pdo pdo_sqlite sqlite3 \
 && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Pre-install dependencies
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader || true

# Copy source
COPY . .

# Generate lock if missing, then re-install
RUN if [ ! -f composer.lock ]; then composer update --no-interaction --no-progress --prefer-dist --optimize-autoloader; fi

# Storage dirs
RUN mkdir -p storage/uploads storage/exports storage/logs \
 && chmod -R 0775 storage

EXPOSE 8080 8282

ENV APP_ENV=production APP_DEBUG=false APP_BASE_URL=http://localhost:8080

CMD ["php", "-S", "0.0.0.0:8080", "-t", "public", "public/index.php"]
