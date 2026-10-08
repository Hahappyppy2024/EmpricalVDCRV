<?php

declare(strict_types=1);

namespace App;

use App\Database\Connection;
use App\Database\Schema;
use App\Middleware\AuthMiddleware;
use App\Middleware\SessionMiddleware;
use Dotenv\Dotenv;
use Slim\Factory\AppFactory;

$rootDir = dirname(__DIR__);

if (file_exists($rootDir . '/.env')) {
    Dotenv::createImmutable($rootDir)->safeLoad();
}

$config = [
    'root_dir' => $rootDir,
    'db_path' => (string) (getenv('DB_PATH') ?: $rootDir . '/data/hosting.db'),
    'storage_dir' => (string) (getenv('STORAGE_DIR') ?: $rootDir . '/storage'),
    'session_lifetime' => (int) (getenv('SESSION_LIFETIME') ?: 86400),
    'app_url' => (string) (getenv('APP_URL') ?: 'http://localhost:8080'),
];

Connection::configure($config['db_path']);
Schema::ensure();

$app = AppFactory::create();

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

$app->add(new AuthMiddleware());
$app->add(new SessionMiddleware());

$errorMiddleware = $app->addErrorMiddleware(false, true, true);
$errorMiddleware->setDefaultErrorHandler(
    function ($request, $throwable) use ($app, $rootDir) {
        $logDir = $rootDir . '/data/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        file_put_contents(
            $logDir . '/error.log',
            '[' . date('Y-m-d H:i:s') . '] ' . (string) $throwable . "\n---\n",
            FILE_APPEND
        );
        $response = $app->getResponseFactory()->createResponse(500);
        $response->getBody()->write(
            '<!DOCTYPE html><html><body><h1>500 Internal Server Error</h1><p>An unexpected error occurred. Please try again later.</p></body></html>'
        );

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
);

require __DIR__ . '/routes.php';

return $app;
