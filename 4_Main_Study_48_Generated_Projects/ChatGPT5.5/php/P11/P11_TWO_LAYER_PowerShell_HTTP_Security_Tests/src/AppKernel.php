<?php
declare(strict_types=1);
namespace App;

use Slim\App;
use Slim\Factory\AppFactory;

final class AppKernel
{
    public static function create(string $root,array $overrides=[]):App
    {
        $config=['appEnv'=>$overrides['APP_ENV']??getenv('APP_ENV')?:'development','dbPath'=>$overrides['DB_PATH']??getenv('DB_PATH')?:$root.'/var/hosting.sqlite','sessionCookie'=>$overrides['SESSION_COOKIE']??getenv('SESSION_COOKIE')?:'hosting_session','sessionTtl'=>(int)($overrides['SESSION_TTL']??getenv('SESSION_TTL')?:86400),'resetTtl'=>(int)($overrides['RESET_TTL']??getenv('RESET_TTL')?:1800)];
        $db=Database::connect($config['dbPath']);$auth=new Auth($db,$config['sessionCookie'],$config['sessionTtl']);$repo=new HostingRepository($db);
        $app=AppFactory::create();$app->addBodyParsingMiddleware();Routes::register($app,$db,$auth,$repo,$config);$app->addRoutingMiddleware();
        $error=$app->addErrorMiddleware(false,false,false);$error->setDefaultErrorHandler(function($request,\Throwable $e)use($app){$r=$app->getResponseFactory()->createResponse();return $e instanceof ApiException?Http::error($r,$e):Http::json($r,['error'=>['code'=>'internal_error','message'=>'The request could not be completed.','fields'=>[]]],500);});return $app;
    }
}
