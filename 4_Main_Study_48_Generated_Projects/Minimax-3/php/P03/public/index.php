<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Shop\Bootstrap;

$app = Bootstrap::app();
$app->run();