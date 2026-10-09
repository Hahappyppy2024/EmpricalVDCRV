<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

use App\Bootstrap;

$app = Bootstrap::build();
$app->run();