<?php

declare(strict_types=1);

namespace App\Controller;

use App\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class PageController
{
    /** @var array<string, array<string, string>> */
    private const PAGES = [
        '/' => ['template' => 'pages/dashboard', 'title' => 'Server Dashboard', 'module' => 'server_dashboard'],
        '/logs' => ['template' => 'pages/logs', 'title' => 'Log Viewer', 'module' => 'log_viewer'],
        '/services' => ['template' => 'pages/services', 'title' => 'Service Control', 'module' => 'service_control'],
        '/jobs' => ['template' => 'pages/jobs', 'title' => 'Job Scheduler', 'module' => 'job_scheduler'],
        '/job-history' => ['template' => 'pages/job_history', 'title' => 'Job Execution History', 'module' => 'job_execution_history'],
        '/backups' => ['template' => 'pages/backups', 'title' => 'Backup Manager', 'module' => 'backup_manager'],
        '/config' => ['template' => 'pages/config', 'title' => 'Configuration Editor', 'module' => 'configuration_editor'],
        '/alerts' => ['template' => 'pages/alerts', 'title' => 'Alert Center', 'module' => 'alert_center'],
        '/health-checks' => ['template' => 'pages/health', 'title' => 'Health Check Targets', 'module' => 'health_check_targets'],
        '/tokens' => ['template' => 'pages/tokens', 'title' => 'API Token Manager', 'module' => 'api_token_manager'],
        '/audit' => ['template' => 'pages/audit', 'title' => 'Audit Logs & Admin Operations', 'module' => 'audit_logs_and_admin_operations'],
        '/account' => ['template' => 'pages/account', 'title' => 'Account Access', 'module' => 'account_access'],
    ];

    public function __construct(private readonly View $view)
    {
    }

    public function page(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        $meta = self::PAGES[$path] ?? self::PAGES['/'];
        $user = $request->getAttribute('user') ?? [];

        $response->getBody()->write($this->view->render($meta['template'], [
            'title' => $meta['title'],
            'module' => $meta['module'],
            'user' => $user,
            'active' => $path,
            'ws' => [
                'url' => $_ENV['WS_URL'] ?? 'ws://127.0.0.1:9090',
                'enabled' => ($_ENV['WS_ENABLED'] ?? '0') === '1',
            ],
        ]));

        return $response;
    }
}
