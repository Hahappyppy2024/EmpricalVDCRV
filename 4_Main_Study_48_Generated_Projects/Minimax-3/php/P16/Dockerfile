FROM php:8.3-cli

ENV APP_ENV=production \
    APP_DEBUG=0

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y --no-install-recommends \
        ca-certificates \
        unzip \
        git \
        libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

COPY composer.json composer.lock ./
COPY src ./src
COPY public ./public
COPY templates ./templates
COPY bin ./bin
COPY .env.example ./.env.example

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer install --no-interaction --no-dev --prefer-dist --optimize-autoloader --no-progress

RUN mkdir -p data data/storage data/storage/backups

EXPOSE 8080

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public", "public/index.php"]