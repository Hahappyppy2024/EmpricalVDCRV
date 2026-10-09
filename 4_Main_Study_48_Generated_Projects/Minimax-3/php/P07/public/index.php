<?php
declare(strict_types=1);
use App\Bootstrap\AppFactory;
use App\Infrastructure\Database;

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../var/php-error.log');

set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        file_put_contents(__DIR__ . '/../var/php-fatal.log', date('c') . ' ' . $err['message'] . ' @ ' . $err['file'] . ':' . $err['line'] . "\n", FILE_APPEND);
    }
});

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/Infrastructure/Database.php';

Database::migrate();

try {
    $app = AppFactory::create();
    $app->run();
} catch (\Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
}