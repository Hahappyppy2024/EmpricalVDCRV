#!/bin/sh
set -e

cd /var/www/html

if [ ! -f data/app.sqlite ] || [ "$DB_RESET_ON_BOOT" = "1" ]; then
    echo "[entrypoint] Initialising SQLite database and seed fixtures..."
    php bin/seed.php --reset
fi

echo "[entrypoint] Starting PHP server on 0.0.0.0:8080"
exec "$@"