<?php
declare(strict_types=1);

/**
 * public/index.php — HTTP entrypoint for the P01 LMS.
 *
 * When run via `php -S 0.0.0.0:8080 -t public public/index.php` this
 * file boots the Slim app and dispatches the request.
 */

$root = dirname(__DIR__);
$autoload = $root . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Composer dependencies are not installed. Run `composer install` first.\n");
    exit(1);
}
require $autoload;

if (class_exists(Dotenv\Dotenv::class) && is_file($root . '/.env')) {
    Dotenv\Dotenv::createImmutable($root)->safeLoad();
}

$config = require $root . '/config/settings.php';

try {
    $app = LMS\AppBuilder::build($config);
    $app->run();
} catch (Throwable $e) {
    $debug = (bool)($config['app']['debug'] ?? false);
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Internal server error.',
        'detail' => $debug ? $e->getMessage() : null,
    ]);
}
