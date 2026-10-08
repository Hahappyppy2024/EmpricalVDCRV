<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
$app=App\AppKernel::create(dirname(__DIR__));$app->run();
