<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;

require dirname(__DIR__) . '/vendor/autoload.php';

$config = new App\Config(dirname(__DIR__));
$app = App\App::create($config);

$app->get('/', function (Request $request, Response $response) {
    return $response->withHeader('Location', '/dashboard')->withStatus(302);
});

// Redirect any unmatched /api path to the JSON 404 handler so unknown modules
// always return a stable JSON error instead of an HTML page.
$app->get('/api/{path:.*}', function (Request $request, Response $response) {
    return App\Http::json($response, 404, ['ok' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Unknown API endpoint']]);
});
$app->post('/api/{path:.*}', function (Request $request, Response $response) {
    return App\Http::json($response, 404, ['ok' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Unknown API endpoint']]);
});
$app->patch('/api/{path:.*}', function (Request $request, Response $response) {
    return App\Http::json($response, 404, ['ok' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Unknown API endpoint']]);
});

$app->run();
