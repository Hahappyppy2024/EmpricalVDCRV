<?php

declare(strict_types=1);

use App\AppBootstrap;
use App\Config;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

if (file_exists($root . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable($root);
    $dotenv->safeLoad();
}

$env = array_merge(getenv() ?: [], $_ENV);
$config = new Config($env);

$app = AppBootstrap::create($config);
$app->run();
