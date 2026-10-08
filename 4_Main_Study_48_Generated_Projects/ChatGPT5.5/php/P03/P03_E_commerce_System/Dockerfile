FROM php:8.3-cli
RUN apt-get update && apt-get install -y --no-install-recommends libsqlite3-dev unzip && docker-php-ext-install pdo_sqlite && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
COPY . .
RUN mkdir -p var/product-images && php bin/reset-database.php
EXPOSE 8080
CMD ["php","-S","0.0.0.0:8080","-t","public","public/router.php"]
