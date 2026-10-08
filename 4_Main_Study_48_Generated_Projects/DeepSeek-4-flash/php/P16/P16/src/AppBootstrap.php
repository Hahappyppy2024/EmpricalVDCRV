<?php

declare(strict_types=1);

namespace App;

use App\Controller\ApiController;
use App\Controller\AuthController;
use App\Controller\PageController;
use App\Middleware\AdminMiddleware;
use App\Middleware\AuthMiddleware;
use App\Modules\AccountAccessService;
use App\Modules\AlertCenterService;
use App\Modules\ApiTokenManagerService;
use App\Modules\AuditLogsAndAdminOperationsService;
use App\Modules\BackupManagerService;
use App\Modules\ConfigurationEditorService;
use App\Modules\HealthCheckTargetsService;
use App\Modules\JobExecutionHistoryService;
use App\Modules\JobSchedulerService;
use App\Modules\LogViewerService;
use App\Modules\ServerDashboardService;
use App\Modules\ServiceControlService;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Routing\RouteCollectorProxy;

final class AppBootstrap
{
    public static function create(Config $config): \Slim\App
    {
        $db = new Database($config->get('db.path'));
        $session = new SessionService($db, $config);
        $audit = new AuditService($db);
        $auth = new AuthService($db, $session);
        $view = new View(dirname(__DIR__) . '/templates');

        /** @var array<string, ModuleService> $services */
        $services = [
            'account_access' => new AccountAccessService($db, $audit, $config),
            'server_dashboard' => new ServerDashboardService($db, $audit, $config),
            'log_viewer' => new LogViewerService($db, $audit, $config),
            'service_control' => new ServiceControlService($db, $audit, $config),
            'job_scheduler' => new JobSchedulerService($db, $audit, $config),
            'job_execution_history' => new JobExecutionHistoryService($db, $audit, $config),
            'backup_manager' => new BackupManagerService($db, $audit, $config),
            'configuration_editor' => new ConfigurationEditorService($db, $audit, $config),
            'alert_center' => new AlertCenterService($db, $audit, $config),
            'health_check_targets' => new HealthCheckTargetsService($db, $audit, $config),
            'api_token_manager' => new ApiTokenManagerService($db, $audit, $config),
            'audit_logs_and_admin_operations' => new AuditLogsAndAdminOperationsService($db, $audit, $config),
        ];

        $app = AppFactory::create();
        $app->addRoutingMiddleware();
        // Slim middleware is LIFO: added last = runs first, so body parsing
        // must be registered after routing to execute before the route handler.
        $app->addBodyParsingMiddleware();

        $errorMiddleware = $app->addErrorMiddleware(true, true, true);
        $errorMiddleware->setDefaultErrorHandler(new ErrorHandler(false));

        $authController = new AuthController($auth, $session, $view);
        $pageController = new PageController($view);
        $apiController = new ApiController($services);
        $authMiddleware = new AuthMiddleware($session);
        $adminMiddleware = new AdminMiddleware();

        $adminModules = [
            'configuration_editor',
            'api_token_manager',
            'audit_logs_and_admin_operations',
        ];

        $app->get('/', $pageController->page(...))->add($authMiddleware);
        foreach (['/logs', '/services', '/jobs', '/job-history', '/backups', '/alerts', '/health-checks', '/account'] as $path) {
            $app->get($path, $pageController->page(...))->add($authMiddleware);
        }
        foreach (['/config', '/tokens', '/audit'] as $path) {
            $app->get($path, $pageController->page(...))->add($adminMiddleware)->add($authMiddleware);
        }

        // Public auth pages and actions
        $app->get('/login', $authController->loginPage(...));
        $app->post('/login', $authController->login(...));
        $app->get('/register', $authController->registerPage(...));
        $app->post('/register', $authController->register(...));
        $app->post('/logout', $authController->logout(...))->add($authMiddleware);

        // Monitoring API: token-authenticated, no session required.
        $app->get('/api/monitor/metrics', function (\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response) use ($services, $db) {
            $token = '';
            $header = (string) ($request->getHeaderLine('Authorization') ?? '');
            if (preg_match('/Bearer\s+(.+)/i', $header, $m)) {
                $token = trim($m[1]);
            } elseif (!empty($request->getQueryParams()['token'])) {
                $token = (string) $request->getQueryParams()['token'];
            }
            /** @var ApiTokenManagerService $api */
            $api = $services['api_token_manager'];
            $valid = $token !== '' ? $api->verify($token) : null;
            if ($valid === null) {
                $response->getBody()->write(json_encode(['ok' => false, 'error' => 'Invalid or missing API token.', 'code' => 'INVALID_TOKEN']));

                return $response->withStatus(401)->withHeader('Content-Type', 'application/json; charset=utf-8');
            }
            /** @var ServerDashboardService $dash */
            $dash = $services['server_dashboard'];
            $response->getBody()->write(json_encode(['ok' => true, 'data' => $dash->latest()], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        });

        $app->get('/api/monitor/health', function (\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response) use ($db) {
            $db->pdo()->query('SELECT 1');
            $response->getBody()->write(json_encode(['ok' => true, 'status' => 'healthy']));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        });

        // Generic module CRUD
        foreach ($services as $module => $service) {
            $api = $apiController;
            $isAdmin = in_array($module, $adminModules, true);

            $guard = static function (\Slim\Routing\Route $route) use ($isAdmin, $adminMiddleware, $authMiddleware): \Slim\Routing\Route {
                // Route middleware is LIFO: the last added middleware runs first.
                if ($isAdmin) {
                    $route->add($adminMiddleware);
                }
                $route->add($authMiddleware);

                return $route;
            };

            $guard($app->get('/api/sys/' . $module, function ($request, $response, $args) use ($api, $module) {
                return $api->list($request, $response, $args, $module);
            }));

            // api_token_manager registers a specialized POST that returns the plain token once.
            if ($module !== 'api_token_manager') {
                $guard($app->post('/api/sys/' . $module, function ($request, $response, $args) use ($api, $module) {
                    return $api->create($request, $response, $args, $module);
                }));
            }

            $guard($app->get('/api/sys/' . $module . '/{id:[0-9]+}', function ($request, $response, $args) use ($api, $module) {
                return $api->item($request, $response, $args, $module);
            }));

            $guard($app->patch('/api/sys/' . $module . '/{id:[0-9]+}', function ($request, $response, $args) use ($api, $module) {
                return $api->update($request, $response, $args, $module);
            }));

            $guard($app->delete('/api/sys/' . $module . '/{id:[0-9]+}', function ($request, $response, $args) use ($api, $module) {
                return $api->delete($request, $response, $args, $module);
            }));
        }

        // Module-specific API endpoints (registered before generic patterns via [0-9]+ guards)
        $app->get('/api/sys/server_dashboard/latest', $apiController->dashboardLatest(...))->add($authMiddleware);
        $app->get('/api/sys/log_viewer/files', $apiController->logFiles(...))->add($authMiddleware);
        $app->get('/api/sys/log_viewer/preview', $apiController->logPreview(...))->add($authMiddleware);
        $app->get('/api/sys/log_viewer/download', $apiController->logDownload(...))->add($authMiddleware);
        $app->post('/api/sys/backup_manager/upload', $apiController->backupUpload(...))->add($authMiddleware);
        $app->get('/api/sys/backup_manager/{id:[0-9]+}/download', $apiController->backupDownload(...))->add($authMiddleware);
        $app->post('/api/sys/backup_manager/{id:[0-9]+}/restore', $apiController->backupRestore(...))->add($authMiddleware);
        $app->post('/api/sys/health_check_targets/{id:[0-9]+}/check', $apiController->healthCheck(...))->add($authMiddleware);
        $app->post('/api/sys/api_token_manager', $apiController->tokenCreate(...))->add($adminMiddleware)->add($authMiddleware);
        $app->get('/api/sys/audit_logs_and_admin_operations/users', $apiController->auditUsers(...))->add($adminMiddleware)->add($authMiddleware);
        $app->get('/api/sys/audit_logs_and_admin_operations/operators', $apiController->auditOperators(...))->add($adminMiddleware)->add($authMiddleware);

        return $app;
    }
}
