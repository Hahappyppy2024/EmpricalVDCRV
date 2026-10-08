<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\HealthController;
use App\Controllers\PageController;
use App\Controllers\WorkflowController;
use App\Middleware\AuthMiddleware;
use Psr\Container\ContainerInterface;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app, ContainerInterface $container): void {
    $auth = $container->get(AuthMiddleware::class);

    // Public
    $app->get('/', PageController::class . ':home');
    $app->get('/login', PageController::class . ':login');
    $app->get('/register', PageController::class . ':register');
    $app->get('/reset-password', PageController::class . ':resetPassword');
    $app->post('/auth/register', AuthController::class . ':register');
    $app->post('/auth/login', AuthController::class . ':login');
    $app->post('/auth/logout', AuthController::class . ':logout');
    $app->post('/auth/reset', AuthController::class . ':reset');
    $app->post('/auth/reset-confirm', AuthController::class . ':resetConfirm');
    $app->get('/healthz', HealthController::class . ':health');

    // Authenticated HTML pages
    $app->group('', function (RouteCollectorProxy $group) use ($container): void {
        $group->get('/dashboard', PageController::class . ':dashboard');
        $group->get('/papers', PageController::class . ':papers');
        $group->get('/papers/{id}', PageController::class . ':paperDetail');
        $group->get('/discovery', PageController::class . ':discovery');
        $group->get('/rebuttal', PageController::class . ':rebuttal');
        $group->get('/account', PageController::class . ':account');
        $group->get('/errors', PageController::class . ':errors');
        $group->get('/manuscripts', PageController::class . ':manuscript');
        $group->get('/blind-views', PageController::class . ':blindViews');

        $group->get('/reviewer', PageController::class . ':reviewer')
            ->add($container->get('reviewer_role'));

        $group->get('/chair', PageController::class . ':chair')
            ->add($container->get('chair_role'));
        $group->get('/chair/phases', PageController::class . ':phases')
            ->add($container->get('chair_role'));
        $group->get('/chair/assignments', PageController::class . ':assignments')
            ->add($container->get('chair_role'));
        $group->get('/chair/decisions', PageController::class . ':decisions')
            ->add($container->get('chair_role'));
        $group->get('/chair/exports', PageController::class . ':exports')
            ->add($container->get('chair_role'));
    })->add($auth);

    // JSON API (all /api routes are authenticated)
    $app->group('/api', function (RouteCollectorProxy $group): void {
        $group->get('/health', HealthController::class . ':health');

        $group->group('/conf', function (RouteCollectorProxy $g): void {
            $g->get('/{module}', WorkflowController::class . ':list');
            $g->post('/{module}', WorkflowController::class . ':create');
            $g->get('/{module}/{id}', WorkflowController::class . ':show');
            $g->patch('/{module}/{id}', WorkflowController::class . ':update');
            $g->get('/manuscript_access/{id}/download', WorkflowController::class . ':downloadManuscript');
            $g->get('/bulk_exports/{id}/download', WorkflowController::class . ':downloadExport');
        });
    })->add($auth);
};
