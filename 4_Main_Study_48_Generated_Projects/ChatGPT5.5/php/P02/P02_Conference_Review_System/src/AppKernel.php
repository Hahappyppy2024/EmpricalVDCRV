<?php
declare(strict_types=1);
namespace App;
use Slim\App;
use Slim\Factory\AppFactory;
use Throwable;
final class AppKernel
{
    public static function create(string $root, array $overrides = []): App
    {
        Env::load($root . '/.env');
        foreach ($overrides as $key => $value) {
            $_ENV[(string)$key] = (string)$value;
        }
        $env = static fn(string $key, string $default): string => (string)($_ENV[$key] ?? getenv($key) ?: $default);
        $db = Database::connect($env('DB_PATH', 'var/conference.sqlite'));
        $auth = new Auth($db, $env('SESSION_COOKIE', 'conf_session'), (int)$env('SESSION_TTL_SECONDS', '86400'));
        $config = ['cookie'=>$env('SESSION_COOKIE','conf_session'),'sessionTtl'=>(int)$env('SESSION_TTL_SECONDS','86400'),'resetTtl'=>(int)$env('PASSWORD_RESET_TTL_SECONDS','1800'),'uploadDir'=>$env('UPLOAD_DIR','var/manuscripts'),'maxUpload'=>(int)$env('MAX_UPLOAD_BYTES','10485760')];
        $app=AppFactory::create();$app->addBodyParsingMiddleware();$app->addRoutingMiddleware();
        AuthRoutes::register($app,$db,$auth,$config);SubmissionRoutes::register($app,$db,$auth,$config);ReviewRoutes::register($app,$db,$auth,$config);
        $errors=$app->addErrorMiddleware(false,true,true);
        $errors->setDefaultErrorHandler(function($request,Throwable $error){if($error instanceof ApiException)return Http::error($error->apiCode,$error->getMessage(),$error->status,$error->fields);error_log($error::class.': '.$error->getMessage());return Http::error('internal_error','The server could not complete the request.',500);});
        return $app;
    }
}
