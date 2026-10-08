#!/bin/sh
# Container entrypoint: initialize the database, start the WebSocket process,
# then serve HTTP in the foreground.
set -e

php /app/bin/reset_db.php

php /app/bin/websocket.php > /app/storage/websocket.log 2>&1 &
echo "WebSocket process started (log: storage/websocket.log)"

exec php -S 0.0.0.0:8080 -t /app/public /app/public/index.php
