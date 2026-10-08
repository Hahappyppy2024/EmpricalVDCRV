#!/bin/sh
set -e

# Deterministic local startup for the P03 E-commerce System container.
#   docker-entrypoint serve   -> web app on :8080
#   docker-entrypoint ws      -> Workerman WebSocket on :8081
#   docker-entrypoint seed    -> reset + seed the SQLite database and exit

export DB_PATH="${DB_PATH:-data/shop.db}"
export APP_URL="${APP_URL:-http://localhost:8080}"
export WS_URL="${WS_URL:-ws://localhost:8081}"
export WS_HOST="${WS_HOST:-0.0.0.0}"
export WS_PORT="${WS_PORT:-8081}"
export UPLOAD_DIR="${UPLOAD_DIR:-public/uploads}"
export LOG_FILE="${LOG_FILE:-storage/logs/error.log}"

command="$1"
if [ -z "$command" ]; then
    command="serve"
fi

case "$command" in
    serve)
        php bin/reset_db.php > /dev/null
        exec php -S 0.0.0.0:8080 -t public public/index.php
        ;;
    ws)
        exec php bin/ws_server.php start
        ;;
    seed)
        php bin/reset_db.php
        ;;
    *)
        echo "Unknown command: $command"
        exit 1
        ;;
esac
