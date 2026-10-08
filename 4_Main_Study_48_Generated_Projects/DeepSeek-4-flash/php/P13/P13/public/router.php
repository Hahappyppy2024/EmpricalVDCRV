<?php

declare(strict_types=1);

/**
 * Router for PHP's built-in development server.
 *
 * Usage: php -S 127.0.0.1:8080 -t public public/router.php
 *
 * Static files are served directly; everything else is handled by the
 * Slim front controller.
 */

$path = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if ($path !== '/' && file_exists(__DIR__ . $path) && !is_dir(__DIR__ . $path)) {
    return false;
}

require __DIR__ . '/index.php';
