<?php
declare(strict_types=1);

namespace App;

use Slim\App;
use Slim\Factory\AppFactory;

final class AppKernel
{
    public static function create(string $root, array $overrides = []): App
    {
        $config = [
            'appEnv' => $overrides['APP_ENV'] ?? getenv('APP_ENV') ?: 'development',
            'dbPath' => $overrides['DB_PATH'] ?? getenv('DB_PATH') ?: $root . '/var/cloud_files.sqlite',
            'uploadDir' => $overrides['UPLOAD_DIR'] ?? getenv('UPLOAD_DIR') ?: $root . '/var/uploads',
            'sessionCookie' => $overrides['SESSION_COOKIE'] ?? getenv('SESSION_COOKIE') ?: 'cloud_session',
            'sessionTtl' => (int)($overrides['SESSION_TTL'] ?? getenv('SESSION_TTL') ?: 86400),
            'resetTtl' => (int)($overrides['RESET_TTL'] ?? getenv('RESET_TTL') ?: 1800),
        ];
        $db = Database::connect($config['dbPath']);
        $auth = new Auth($db, $config['sessionCookie'], $config['sessionTtl']);
        $repo = new CloudRepository($db, $config['uploadDir']);
        $app = AppFactory::create();
        $app->addBodyParsingMiddleware();
        AuthAdminRoutes::register($app, $db, $auth, $repo, $config);
        FileFolderRoutes::register($app, $db, $auth, $repo);
        TeamTrashRoutes::register($app, $db, $auth, $repo, $config);
        $app->addRoutingMiddleware();
        $error = $app->addErrorMiddleware($config['appEnv'] === 'development', false, false);
        $error->setDefaultErrorHandler(function ($request, \Throwable $exception) use ($app) {
            $response = $app->getResponseFactory()->createResponse();
            if ($exception instanceof ApiException) {
                return Http::error($response, $exception);
            }
            return Http::json($response, ['error' => ['code' => 'internal_error', 'message' => 'The request could not be completed.', 'fields' => []]], 500);
        });
        return $app;
    }
}
