FROM php:8.3-cli
RUN docker-php-ext-install pdo_sqlite
WORKDIR /app
COPY . .
RUN php bin/reset-database.php
EXPOSE 8080
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public", "public/router.php"]
