<?php

declare(strict_types=1);

namespace App;

use App\Controllers\AccountAccessController;
use App\Controllers\AlertCenterController;
use App\Controllers\ApiTokenManagerController;
use App\Controllers\AuditLogsController;
use App\Controllers\BackupManagerController;
use App\Controllers\ConfigurationEditorController;
use App\Controllers\HealthCheckTargetsController;
use App\Controllers\JobExecutionHistoryController;
use App\Controllers\JobSchedulerController;
use App\Controllers\LogViewerController;
use App\Controllers\ServerDashboardController;
use App\Controllers\ServiceControlController;
use App\Database\Migrator;
use App\Repositories\AlertRepository;
use App\Repositories\ApiTokenRepository;
use App\Repositories\AuditEventRepository;
use App\Repositories\ConfigurationRepository;
use App\Repositories\HealthCheckRepository;
use App\Repositories\JobProfileRepository;
use App\Repositories\JobRunRepository;
use App\Repositories\LogEntryRepository;
use App\Repositories\LogFileRepository;
use App\Repositories\MetricSnapshotRepository;
use App\Repositories\ScheduledJobRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\StoredFileRepository;
use App\Repositories\UserRepository;
use App\Services\AuditService;
use App\Services\SessionService;
use Dotenv\Dotenv;
use PDOException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App as SlimApp;
use Slim\Factory\AppFactory;
use Slim\Views\PhpRenderer;

/**
 * Application bootstrap. Wires every dependency to the Slim app.
 */
final class App
{
    public static function boot(array $overrides = []): SlimApp
    {
        self::loadEnvironment();
        self::configurePhp();

        $config = self::resolveConfig($overrides);

        self::ensureDatabaseFresh($config);

        $app = AppFactory::create();
        $app->addBodyParsingMiddleware();
        $app->addRoutingMiddleware();

        $errorMiddleware = $app->addErrorMiddleware(
            (bool)($config['app.debug'] ?? false),
            true,
            true
        );
        $errorMiddleware->setDefaultErrorHandler(function (
            Request $request,
            \Throwable $exception,
            bool $displayErrorDetails
        ) use ($app) {
            $payload = [
                'ok' => false,
                'error' => $exception->getMessage(),
            ];
            $response = $app->getResponseFactory()->createResponse();
            $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_SLASHES));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        });

        $renderer = new PhpRenderer(__DIR__ . '/../templates', [
            'app_name' => $config['app.name'],
        ]);

        $session = new SessionService((int)$config['session.lifetime']);

        $userRepo = new UserRepository();
        $metricRepo = new MetricSnapshotRepository();
        $logFileRepo = new LogFileRepository();
        $logEntryRepo = new LogEntryRepository();
        $serviceRepo = new ServiceRepository();
        $profileRepo = new JobProfileRepository();
        $jobRepo = new ScheduledJobRepository();
        $runRepo = new JobRunRepository();
        $fileRepo = new StoredFileRepository();
        $configRepo = new ConfigurationRepository();
        $alertRepo = new AlertRepository();
        $healthRepo = new HealthCheckRepository();
        $tokenRepo = new ApiTokenRepository();
        $auditRepo = new AuditEventRepository();
        $auditService = new AuditService();

        $controllers = [
            'account'   => new AccountAccessController($session, $userRepo, $auditService, $renderer),
            'dashboard' => new ServerDashboardController($session, $metricRepo, $serviceRepo, $alertRepo, $healthRepo, $runRepo, $fileRepo, $auditRepo, $renderer),
            'logs'      => new LogViewerController($session, $logFileRepo, $logEntryRepo, $auditService, $renderer),
            'services'  => new ServiceControlController($session, $serviceRepo, $auditService, $renderer),
            'jobs'      => new JobSchedulerController($session, $jobRepo, $profileRepo, $runRepo, $auditService, $renderer),
            'history'   => new JobExecutionHistoryController($session, $jobRepo, $runRepo, $renderer),
            'backup'    => new BackupManagerController($session, $fileRepo, $auditService, $renderer, (string)$config['storage.path']),
            'config'    => new ConfigurationEditorController($session, $configRepo, $auditService, $renderer),
            'alerts'    => new AlertCenterController($session, $alertRepo, $userRepo, $auditService, $renderer),
            'health'    => new HealthCheckTargetsController($session, $healthRepo, $auditService, $renderer),
            'tokens'    => new ApiTokenManagerController($session, $tokenRepo, $auditService, $renderer),
            'audit'     => new AuditLogsController($session, $auditRepo, $userRepo, $auditService, $renderer),
        ];

        self::registerRoutes($app, $controllers, $session);

        return $app;
    }

    public static function resetDatabase(array $overrides = []): void
    {
        self::loadEnvironment();
        self::configurePhp();
        $config = self::resolveConfig($overrides);
        self::ensureDatabaseFresh($config);
    }

    private static function loadEnvironment(): void
    {
        $root = dirname(__DIR__);
        if (file_exists($root . '/.env')) {
            Dotenv::createImmutable($root)->safeLoad();
        }
    }

    private static function configurePhp(): void
    {
        date_default_timezone_set((string)($_ENV['APP_TIMEZONE'] ?? 'UTC'));
        if (PHP_SAPI !== 'cli' && !headers_sent() && session_status() !== PHP_SESSION_ACTIVE) {
            ini_set('session.use_cookies', '0');
            session_start([
                'use_cookies' => '0',
                'use_only_cookies' => '0',
                'name' => 'p16_php_session',
            ]);
        }
    }

    /**
     * @return array<string,mixed>
     */
    private static function resolveConfig(array $overrides): array
    {
        $root = dirname(__DIR__);
        $config = [
            'app.env' => $_ENV['APP_ENV'] ?? 'local',
            'app.name' => $_ENV['APP_NAME'] ?? 'P16',
            'app.debug' => filter_var($_ENV['APP_DEBUG'] ?? '1', FILTER_VALIDATE_BOOL),
            'db.path' => self::resolvePath($root, (string)($_ENV['DB_PATH'] ?? 'data/app.sqlite')),
            'db.reset_on_boot' => filter_var($_ENV['DB_RESET_ON_BOOT'] ?? '0', FILTER_VALIDATE_BOOL),
            'storage.path' => self::resolvePath($root, (string)($_ENV['STORAGE_PATH'] ?? 'data/storage')),
            'session.lifetime' => (int)($_ENV['SESSION_LIFETIME_MINUTES'] ?? 120),
            'seed.admin_username' => $_ENV['SEED_ADMIN_USERNAME'] ?? 'admin',
            'seed.admin_password' => $_ENV['SEED_ADMIN_PASSWORD'] ?? 'Admin#12345',
            'seed.operator_username' => $_ENV['SEED_OPERATOR_USERNAME'] ?? 'operator',
            'seed.operator_password' => $_ENV['SEED_OPERATOR_PASSWORD'] ?? 'Operator#12345',
        ];
        foreach ($overrides as $k => $v) {
            $config[$k] = $v;
        }
        if (!is_dir(dirname((string)$config['db.path']))) {
            mkdir(dirname((string)$config['db.path']), 0777, true);
        }
        if (!is_dir((string)$config['storage.path'])) {
            mkdir((string)$config['storage.path'], 0777, true);
        }
        return $config;
    }

    private static function ensureDatabaseFresh(array $config): void
    {
        $shouldReset = (bool)($config['db.reset_on_boot'] ?? false);
        $expectedPath = (string)$config['db.path'];
        \App\Database\Connection::configure($expectedPath);
        if (!$shouldReset && file_exists($expectedPath)) {
            // Schema only needs to be applied on first boot; ensure tables exist via CREATE IF NOT EXISTS.
            $migrator = new Migrator(__DIR__ . '/Database/schema.php');
            $migrator->migrate();
            return;
        }
        $migrator = new Migrator(__DIR__ . '/Database/schema.php');
        $migrator->fresh(true);
        self::seedDeterministicFixtures($config);
    }

    private static function seedDeterministicFixtures(array $config): void
    {
        \App\Database\Connection::reset();
        \App\Database\Connection::configure((string)$config['db.path']);
        $seed = new \App\Seed\Seeder($config);
        $seed->run();
    }

    private static function resolvePath(string $root, string $relative): string
    {
        if ($relative === '') {
            return $root;
        }
        if (preg_match('#^(?:[A-Za-z]:)?[\\\\/]#', $relative)) {
            return $relative;
        }
        return $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
    }

    /**
     * @param array<string,object> $controllers
     */
    private static function registerRoutes(SlimApp $app, array $controllers, SessionService $session): void
    {
        $root = $app->get('/[/]', function (Request $request, Response $response) use ($session) {
            $current = $session->start();
            $target = $current ? ($current['role'] === 'admin' ? '/admin' : '/dashboard') : '/login';
            return $response->withHeader('Location', $target)->withStatus(302);
        });

        $app->get('/login', [$controllers['account'], 'showLogin']);
        $app->post('/login', [$controllers['account'], 'login']);
        $app->get('/register', [$controllers['account'], 'showRegister']);
        $app->post('/register', [$controllers['account'], 'register']);
        $app->post('/logout', [$controllers['account'], 'logout']);
        $app->get('/logout', [$controllers['account'], 'logout']);

        $app->get('/dashboard', [$controllers['dashboard'], 'showPage']);
        $app->get('/admin', function (Request $request, Response $response) {
            return $response->withHeader('Location', '/dashboard')->withStatus(302);
        });

        $app->get('/logs', [$controllers['logs'], 'showPage']);
        $app->get('/logs/download/{id:[0-9]+}', [$controllers['logs'], 'download']);

        $app->get('/services', [$controllers['services'], 'showPage']);
        $app->post('/services/{id:[0-9]+}/act', [$controllers['services'], 'act']);

        $app->get('/jobs', [$controllers['jobs'], 'showPage']);
        $app->post('/jobs', [$controllers['jobs'], 'create']);
        $app->post('/jobs/{id:[0-9]+}/transition', [$controllers['jobs'], 'transition']);
        $app->post('/jobs/{id:[0-9]+}/run', [$controllers['jobs'], 'run']);
        $app->get('/job_runs', [$controllers['history'], 'showIndex']);
        $app->get('/job_runs/{id:[0-9]+}', [$controllers['history'], 'showForJob']);
        $app->get('/job_runs/run/{id:[0-9]+}', [$controllers['history'], 'showRun']);

        $app->get('/backups', [$controllers['backup'], 'showPage']);
        $app->post('/backups', [$controllers['backup'], 'upload']);
        $app->get('/backups/download/{id:[0-9]+}', [$controllers['backup'], 'download']);
        $app->post('/backups/{id:[0-9]+}/delete', [$controllers['backup'], 'delete']);

        $app->get('/configuration', [$controllers['config'], 'showPage']);
        $app->post('/configuration', [$controllers['config'], 'upsert']);
        $app->post('/configuration/{id:[0-9]+}/stage', [$controllers['config'], 'stage']);
        $app->post('/configuration/{id:[0-9]+}/approve', [$controllers['config'], 'approve']);
        $app->post('/configuration/{id:[0-9]+}/reject', [$controllers['config'], 'reject']);

        $app->get('/alerts', [$controllers['alerts'], 'showPage']);
        $app->post('/alerts', [$controllers['alerts'], 'create']);
        $app->post('/alerts/{id:[0-9]+}', [$controllers['alerts'], 'update']);

        $app->get('/health_targets', [$controllers['health'], 'showPage']);
        $app->post('/health_targets', [$controllers['health'], 'create']);
        $app->post('/health_targets/{id:[0-9]+}/check', [$controllers['health'], 'check']);
        $app->post('/health_targets/{id:[0-9]+}/delete', [$controllers['health'], 'delete']);

        $app->get('/api_tokens', [$controllers['tokens'], 'showPage']);
        $app->post('/api_tokens', [$controllers['tokens'], 'create']);
        $app->post('/api_tokens/{id:[0-9]+}/revoke', [$controllers['tokens'], 'revoke']);

        $app->get('/audit', [$controllers['audit'], 'showPage']);
        $app->post('/audit/users', [$controllers['audit'], 'manageUsers']);

        self::registerApiRoutes($app, $controllers);
    }

    /**
     * @param array<string,object> $controllers
     */
    private static function registerApiRoutes(SlimApp $app, array $controllers): void
    {
        $app->get('/api/sys/account_access', [$controllers['account'], 'apiIndex']);
        $app->post('/api/sys/account_access', [$controllers['account'], 'apiCreate']);
        $app->patch('/api/sys/account_access/{id:[0-9]+}', [$controllers['account'], 'apiPatch']);

        $app->get('/api/sys/server_dashboard', [$controllers['dashboard'], 'apiIndex']);
        $app->post('/api/sys/server_dashboard', [$controllers['dashboard'], 'apiCreate']);

        $app->get('/api/sys/log_viewer', [$controllers['logs'], 'apiIndex']);
        $app->post('/api/sys/log_viewer', [$controllers['logs'], 'apiCreate']);

        $app->get('/api/sys/service_control', [$controllers['services'], 'apiIndex']);
        $app->post('/api/sys/service_control', [$controllers['services'], 'apiCreate']);
        $app->patch('/api/sys/service_control/{id:[0-9]+}', [$controllers['services'], 'apiPatch']);

        $app->get('/api/sys/job_scheduler', [$controllers['jobs'], 'apiIndex']);
        $app->post('/api/sys/job_scheduler', [$controllers['jobs'], 'apiCreate']);
        $app->patch('/api/sys/job_scheduler/{id:[0-9]+}', [$controllers['jobs'], 'apiPatch']);

        $app->get('/api/sys/job_execution_history', [$controllers['history'], 'apiIndex']);
        $app->patch('/api/sys/job_execution_history/{id:[0-9]+}', [$controllers['history'], 'apiPatch']);

        $app->get('/api/sys/backup_manager', [$controllers['backup'], 'apiIndex']);
        $app->post('/api/sys/backup_manager', [$controllers['backup'], 'apiCreate']);
        $app->patch('/api/sys/backup_manager/{id:[0-9]+}', [$controllers['backup'], 'apiPatch']);

        $app->get('/api/sys/configuration_editor', [$controllers['config'], 'apiIndex']);
        $app->post('/api/sys/configuration_editor', [$controllers['config'], 'apiCreate']);
        $app->patch('/api/sys/configuration_editor/{id:[0-9]+}', [$controllers['config'], 'apiPatch']);

        $app->get('/api/sys/alert_center', [$controllers['alerts'], 'apiIndex']);
        $app->post('/api/sys/alert_center', [$controllers['alerts'], 'apiCreate']);
        $app->patch('/api/sys/alert_center/{id:[0-9]+}', [$controllers['alerts'], 'apiPatch']);

        $app->get('/api/sys/health_check_targets', [$controllers['health'], 'apiIndex']);
        $app->post('/api/sys/health_check_targets', [$controllers['health'], 'apiCreate']);
        $app->patch('/api/sys/health_check_targets/{id:[0-9]+}', [$controllers['health'], 'apiPatch']);

        $app->get('/api/sys/api_token_manager', [$controllers['tokens'], 'apiIndex']);
        $app->post('/api/sys/api_token_manager', [$controllers['tokens'], 'apiCreate']);
        $app->patch('/api/sys/api_token_manager/{id:[0-9]+}', [$controllers['tokens'], 'apiPatch']);

        $app->get('/api/sys/audit_logs_and_admin_operations', [$controllers['audit'], 'apiIndex']);
        $app->post('/api/sys/audit_logs_and_admin_operations', [$controllers['audit'], 'apiCreate']);
        $app->patch('/api/sys/audit_logs_and_admin_operations/{id:[0-9]+}', [$controllers['audit'], 'apiPatch']);
    }
}