<?php

declare(strict_types=1);

/**
 * Router for the PHP built-in server. Serves static files directly and
 * delegates everything else to the Slim front controller.
 */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$file = __DIR__ . $path;

if ($path !== '/' && is_file($file) && strpos($path, '..') === false) {
    return false;
}

require __DIR__ . '/index.php';
