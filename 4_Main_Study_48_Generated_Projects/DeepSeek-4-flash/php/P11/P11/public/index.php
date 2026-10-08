<?php

declare(strict_types=1);

use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../src/bootstrap.php';

$app->run();
