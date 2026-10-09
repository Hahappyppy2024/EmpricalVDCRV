#!/bin/sh
set -e

# On first start, reset the database so deterministic seed data is applied.
if [ ! -f "$APP_DB_PATH" ]; then
    echo "[entrypoint] Initializing database at $APP_DB_PATH"
    /app/seed
fi

exec "$@"