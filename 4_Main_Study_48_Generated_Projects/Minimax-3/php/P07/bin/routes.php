<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/Infrastructure/Database.php';
require __DIR__ . '/../src/Bootstrap/AppFactory.php';
use App\Bootstrap\AppFactory;
use App\Infrastructure\Database;
Database::migrate();
$app = AppFactory::create();
foreach ($app->getRouteCollector()->getRoutes() as $route) {
    $methods = implode(',', $route->getMethods());
    echo $methods . ' ' . $route->getPattern() . PHP_EOL;
}