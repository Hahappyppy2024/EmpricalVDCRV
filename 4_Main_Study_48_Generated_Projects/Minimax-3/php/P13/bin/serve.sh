#!/usr/bin/env bash
# Linux/macOS launcher
cd "$(dirname "$0")/.."
exec php -S 127.0.0.1:${APP_PORT:-8080} -t public public/index.php
