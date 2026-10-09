<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\Services\SessionService;
use App\Services\View;
use App\Services\AuditService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class DashboardController
{
    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $config = $GLOBALS['app_config'];
        SessionService::requireAuth();
        $user = SessionService::user();
        $body = View::render('dashboard', ['user' => $user], $config);
        return View::html(View::layout('Dashboard', $body, $config, $user));
    }

    public function home(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $config = $GLOBALS['app_config'];
        $user = SessionService::user();
        $body = View::render('home', [], $config);
        return View::html(View::layout($config['app']['name'], $body, $config, $user));
    }
}