<?php

declare(strict_types=1);

use CloudFS\App;
use CloudFS\Config;

define('CLOUDFS_ROOT', dirname(__DIR__));

require CLOUDFS_ROOT . '/vendor/autoload.php';

if (function_exists('date_default_timezone_set')) {
    $tz = getenv('APP_TIMEZONE') ?: 'UTC';
    date_default_timezone_set($tz);
}

$config = Config::load(CLOUDFS_ROOT);
$app = App::create($config);
$app->run();
