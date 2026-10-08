<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

// Built-in PHP dev server: serve existing static files directly.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (is_string($path) && $path !== '/') {
    $public = realpath(__DIR__);
    $candidate = realpath(__DIR__ . $path);
    if ($candidate !== false && is_file($candidate) && $public !== false && str_starts_with($candidate, $public . DIRECTORY_SEPARATOR)) {
        return false;
    }
}

$app = Shop\App::create();
$app->run();
