# Use official PHP image with required extensions
FROM php:8.3-cli AS base

# Install system deps and PHP extensions
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      libsqlite3-dev \
      unzip \
      git \
      ca-certificates \
 && docker-php-ext-install pdo pdo_sqlite \
 && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Pre-copy composer files for caching
COPY composer.json ./
RUN composer install --no-interaction --prefer-dist --no-scripts || true

# Copy the application code
COPY . /app

# Re-run composer to make sure autoload is fresh
RUN composer dump-autoload --optimize

# Prepare writable storage
RUN mkdir -p storage/data storage/uploads storage/backups \
 && chown -R www-data:www-data storage

# Make the init script executable
RUN chmod +x bin/init-db.php || true

ENV APP_ENV=production \
    APP_DEBUG=false \
    DB_PATH=/app/storage/data/panel.sqlite \
    UPLOAD_DIR=/app/storage/uploads \
    BACKUP_DIR=/app/storage/backups

EXPOSE 8080

CMD ["sh", "-c", "php bin/init-db.php && php -S 0.0.0.0:8080 -t public public/index.php"]