<?php
declare(strict_types=1);

use App\AppKernel;

require dirname(__DIR__) . '/vendor/autoload.php';
AppKernel::create(dirname(__DIR__))->run();
