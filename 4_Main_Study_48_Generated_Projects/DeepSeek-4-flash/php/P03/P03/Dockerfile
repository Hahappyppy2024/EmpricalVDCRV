FROM php:8.3-cli

# System dependencies for Composer + PHP extensions
RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip git curl \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions required by the application and Composer
RUN docker-php-ext-install pdo_sqlite fileinfo \
    && docker-php-ext-enable pdo_sqlite fileinfo

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Dependencies first for better layer caching
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

# Application source
COPY . .

RUN mkdir -p /app/data /app/storage/logs /app/public/uploads \
    && chmod -R 0777 /app/data /app/storage /app/public/uploads

EXPOSE 8080 8081

COPY bin/docker_entrypoint.sh /usr/local/bin/docker-entrypoint
RUN chmod +x /usr/local/bin/docker-entrypoint

ENTRYPOINT ["docker-entrypoint"]
CMD ["serve"]
