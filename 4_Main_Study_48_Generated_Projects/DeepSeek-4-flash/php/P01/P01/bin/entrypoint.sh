#!/bin/sh
set -e

# Initialize the SQLite database with deterministic seed fixtures.
php bin/reset.php

# Start the Workerman WebSocket process in the background (shares SQLite).
php bin/websocket.php start &
WS_PID=$!

# Start the PHP development server on port 8080.
exec php -S 0.0.0.0:8080 -t public
