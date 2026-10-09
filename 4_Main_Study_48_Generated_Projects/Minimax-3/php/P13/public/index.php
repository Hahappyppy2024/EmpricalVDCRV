<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use MailServer\Application;

$app = Application::boot();
$app->run();