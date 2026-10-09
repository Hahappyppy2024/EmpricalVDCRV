<?php
declare(strict_types=1);

namespace App;

use App\Auth\SessionService;
use App\Controllers\AccountController;
use App\Controllers\AdminController;
use App\Controllers\AuditController;
use App\Controllers\AuthController;
use App\Controllers\BackupController;
use App\Controllers\CronController;
use App\Controllers\DatabaseManagementController;
use App\Controllers\DashboardController;
use App\Controllers\DomainController;
use App\Controllers\FileManagerController;
use App\Controllers\ResourceController;
use App\Controllers\SiteController;
use App\Controllers\SslController;
use App\Controllers\TicketController;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\RoleMiddleware;
use App\Repositories\AuditRepository;
use App\Repositories\BackupRepository;
use App\Repositories\CronRepository;
use App\Repositories\DatabaseRepository;
use App\Repositories\DomainRepository;
use App\Repositories\FileRepository;
use App\Repositories\PlanRepository;
use App\Repositories\ResourceRepository;
use App\Repositories\SettingsRepository;
use App\Repositories\SiteRepository;
use App\Repositories\SslRepository;
use App\Repositories\TicketRepository;
use App\Repositories\UserRepository;
use DI\Container;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Factory\AppFactory;

final class Bootstrap
{
    public static function build(): App
    {
        Config::load();

        $container = new Container();
        AppFactory::setContainer($container);

        $container->set(SessionService::class, fn() => new SessionService());
        $container->set(UserRepository::class, fn() => new UserRepository());
        $container->set(PlanRepository::class, fn() => new PlanRepository());
        $container->set(DomainRepository::class, fn() => new DomainRepository());
        $container->set(SiteRepository::class, fn() => new SiteRepository());
        $container->set(FileRepository::class, fn() => new FileRepository());
        $container->set(DatabaseRepository::class, fn() => new DatabaseRepository());
        $container->set(BackupRepository::class, fn() => new BackupRepository());
        $container->set(SslRepository::class, fn() => new SslRepository());
        $container->set(CronRepository::class, fn() => new CronRepository());
        $container->set(ResourceRepository::class, fn() => new ResourceRepository());
        $container->set(TicketRepository::class, fn() => new TicketRepository());
        $container->set(AuditRepository::class, fn() => new AuditRepository());
        $container->set(SettingsRepository::class, fn() => new SettingsRepository());

        $app = AppFactory::create();

        $app->addBodyParsingMiddleware();
        $app->addRoutingMiddleware();

        $errorMw = function (ServerRequestInterface $request, \Psr\Http\Server\RequestHandlerInterface $handler) {
            try {
                return $handler->handle($request);
            } catch (\Throwable $e) {
                $resp = new \Slim\Psr7\Response(500);
                $resp->getBody()->write('Server error');
                if (getenv('APP_DEBUG') === 'true') {
                    $resp->getBody()->write(': ' . htmlspecialchars($e->getMessage()));
                }
                return $resp;
            }
        };

        $app->add($errorMw);
        $app->add(new CsrfMiddleware());

        // Static assets
        $app->get('/assets/{file:.+}', function (ServerRequestInterface $req, ResponseInterface $res, array $args) {
            $path = dirname(__DIR__) . '/public/assets/' . $args['file'];
            if (!is_file($path)) return $res->withStatus(404);
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $types = ['css' => 'text/css', 'js' => 'application/javascript', 'png' => 'image/png', 'svg' => 'image/svg+xml', 'ico' => 'image/x-icon'];
            $type = $types[$ext] ?? 'application/octet-stream';
            $res->getBody()->write(file_get_contents($path));
            return $res->withHeader('Content-Type', $type);
        });

        // Public auth routes (no auth required)
        $sessionService = $container->get(SessionService::class);
        $userRepo = $container->get(UserRepository::class);

        $app->get('/', function ($req, $res) { return View::redirect($res, '/dashboard'); });
        $authCtrl = new AuthController($sessionService, $userRepo);
        $app->get('/login',     [$authCtrl, 'loginPage']);
        $app->post('/login',    [$authCtrl, 'loginPost']);
        $app->get('/register',  [$authCtrl, 'registerPage']);
        $app->post('/register', [$authCtrl, 'registerPost']);

        // ----- Protected web routes -----
        // AuthMiddleware is applied via $app->add() and checks the path inside,
        // because Slim 4 doesn't support $group->add() inside group callbacks.
        // We instead apply AuthMiddleware as App-level and let it short-circuit
        // for whitelisted paths via path inspection.
        $app->add(new AuthMiddleware($sessionService));

        $auth = $authCtrl;
        $app->post('/logout', [$auth, 'logout']);
        $app->post('/account/password', [$auth, 'changePassword']);
        $accountCtrl = new AccountController($userRepo);
        $app->get('/account', [$accountCtrl, 'show']);

        $dash = new DashboardController(
            $container->get(DomainRepository::class),
            $container->get(SiteRepository::class),
            $container->get(TicketRepository::class),
            $container->get(ResourceRepository::class),
            $container->get(BackupRepository::class),
        );
        $app->get('/dashboard', [$dash, 'home']);

        $dom = new DomainController($container->get(DomainRepository::class));
        $app->get('/domains',                 [$dom, 'index']);
        $app->get('/domains/{id:\d+}',        [$dom, 'show']);
        $app->post('/domains',                [$dom, 'create']);
        $app->patch('/domains/{id:\d+}',      [$dom, 'update']);
        $app->post('/domains/{id:\d+}/update',[$dom, 'update']);
        $app->delete('/domains/{id:\d+}',     [$dom, 'delete']);
        $app->post('/domains/{id:\d+}/delete',[$dom, 'delete']);
        $app->post('/domains/{id:\d+}/records', [$dom, 'addRecord']);
        $app->delete('/domains/{id:\d+}/records/{rid:\d+}', [$dom, 'deleteRecord']);

        $site = new SiteController($container->get(SiteRepository::class), $container->get(DomainRepository::class));
        $app->get('/sites',                 [$site, 'index']);
        $app->get('/sites/{id:\d+}',        [$site, 'show']);
        $app->post('/sites',                [$site, 'create']);
        $app->patch('/sites/{id:\d+}',      [$site, 'update']);
        $app->post('/sites/{id:\d+}/update',[$site, 'update']);
        $app->delete('/sites/{id:\d+}',     [$site, 'delete']);
        $app->post('/sites/{id:\d+}/delete',[$site, 'delete']);

        $fm = new FileManagerController($container->get(FileRepository::class), $container->get(SiteRepository::class));
        $app->get('/files',                 [$fm, 'index']);
        $app->get('/files/{id:\d+}',        [$fm, 'show']);
        $app->post('/files',                [$fm, 'upload']);
        $app->patch('/files/{id:\d+}',      [$fm, 'rename']);
        $app->post('/files/{id:\d+}/rename',[$fm, 'rename']);
        $app->delete('/files/{id:\d+}',     [$fm, 'delete']);
        $app->post('/files/{id:\d+}/delete',[$fm, 'delete']);

        $db = new DatabaseManagementController($container->get(DatabaseRepository::class));
        $app->get('/databases',                 [$db, 'index']);
        $app->post('/databases',                [$db, 'create']);
        $app->patch('/databases/{id:\d+}',      [$db, 'update']);
        $app->post('/databases/{id:\d+}/update',[$db, 'update']);
        $app->delete('/databases/{id:\d+}',     [$db, 'delete']);
        $app->post('/databases/{id:\d+}/delete',[$db, 'delete']);

        $bk = new BackupController($container->get(BackupRepository::class));
        $app->get('/backups',                 [$bk, 'index']);
        $app->post('/backups',                [$bk, 'create']);
        $app->post('/backups/upload',         [$bk, 'upload']);
        $app->post('/backups/{id:\d+}/restore', [$bk, 'restore']);
        $app->delete('/backups/{id:\d+}',     [$bk, 'delete']);
        $app->post('/backups/{id:\d+}/delete',[$bk, 'delete']);

        $ssl = new SslController($container->get(SslRepository::class), $container->get(DomainRepository::class));
        $app->get('/ssl',                 [$ssl, 'index']);
        $app->post('/ssl',                [$ssl, 'create']);
        $app->post('/ssl/{id:\d+}/renew', [$ssl, 'renew']);
        $app->post('/ssl/{id:\d+}/revoke',[$ssl, 'revoke']);

        $cron = new CronController($container->get(CronRepository::class));
        $app->get('/cron',                 [$cron, 'index']);
        $app->post('/cron',                [$cron, 'create']);
        $app->patch('/cron/{id:\d+}',      [$cron, 'update']);
        $app->post('/cron/{id:\d+}/update',[$cron, 'update']);
        $app->post('/cron/{id:\d+}/run',   [$cron, 'run']);
        $app->delete('/cron/{id:\d+}',     [$cron, 'delete']);
        $app->post('/cron/{id:\d+}/delete',[$cron, 'delete']);

        $res = new ResourceController($container->get(ResourceRepository::class));
        $app->get('/resources',           [$res, 'index']);
        $app->post('/resources',          [$res, 'record']);
        $app->patch('/resources/{id:\d+}',[$res, 'update']);

        $tk = new TicketController($container->get(TicketRepository::class));
        $app->get('/tickets',              [$tk, 'index']);
        $app->get('/tickets/{id:\d+}',     [$tk, 'show']);
        $app->post('/tickets',             [$tk, 'create']);
        $app->post('/tickets/{id:\d+}/reply', [$tk, 'reply']);
        $app->patch('/tickets/{id:\d+}',   [$tk, 'setStatus']);
        $app->post('/tickets/{id:\d+}/status', [$tk, 'setStatus']);
        $app->post('/tickets/{id:\d+}/assign', [$tk, 'assign']);

        $au = new AuditController($container->get(AuditRepository::class));
        $app->get('/audit',            [$au, 'index']);
        $app->get('/audit/{id:\d+}',   [$au, 'show']);
        $app->post('/audit',           [$au, 'create']);

        // ----- Admin routes (admin role only) -----
        $adminCtrl = new AdminController(
            $container->get(UserRepository::class),
            $container->get(PlanRepository::class),
            $container->get(SettingsRepository::class),
        );
        $adminGroup = $app->group('/admin', function ($g) use ($adminCtrl) {
            $g->get('',                       [$adminCtrl, 'index']);
            $g->post('/users',                [$adminCtrl, 'createUser']);
            $g->patch('/users/{id:\d+}',      [$adminCtrl, 'updateUser']);
            $g->post('/users/{id:\d+}/update',[$adminCtrl, 'updateUser']);
            $g->post('/settings',             [$adminCtrl, 'updateSetting']);
        });
        $adminGroup->add(new RoleMiddleware(['admin']));

        // ----- API routes -----
        // For the API group, we want the same AuthMiddleware behavior.
        // AuthMiddleware was added at App-level already.
        $apiGroup = $app->group('/api/host', function ($g) use ($container) {
            $auth = new AuthController($container->get(SessionService::class), $container->get(UserRepository::class));
            $g->get('/account_access', [$auth, 'loginPage']);
            $g->post('/account_access', [$auth, 'loginPost']);
            $g->patch('/account_access/{id:\d+}', [$auth, 'changePassword']);

            $dom = new DomainController($container->get(DomainRepository::class));
            $g->get('/domain_management',          [$dom, 'index']);
            $g->post('/domain_management',         [$dom, 'create']);
            $g->get('/domain_management/{id:\d+}', [$dom, 'show']);
            $g->patch('/domain_management/{id:\d+}', [$dom, 'update']);
            $g->delete('/domain_management/{id:\d+}', [$dom, 'delete']);
            $g->post('/domain_management/{id:\d+}/records', [$dom, 'addRecord']);
            $g->delete('/domain_management/{id:\d+}/records/{rid:\d+}', [$dom, 'deleteRecord']);

            $site = new SiteController($container->get(SiteRepository::class), $container->get(DomainRepository::class));
            $g->get('/site_management',          [$site, 'index']);
            $g->post('/site_management',         [$site, 'create']);
            $g->get('/site_management/{id:\d+}', [$site, 'show']);
            $g->patch('/site_management/{id:\d+}', [$site, 'update']);
            $g->delete('/site_management/{id:\d+}', [$site, 'delete']);

            $fm = new FileManagerController($container->get(FileRepository::class), $container->get(SiteRepository::class));
            $g->get('/file_manager',          [$fm, 'index']);
            $g->post('/file_manager',         [$fm, 'upload']);
            $g->get('/file_manager/{id:\d+}', [$fm, 'show']);
            $g->patch('/file_manager/{id:\d+}', [$fm, 'rename']);
            $g->delete('/file_manager/{id:\d+}', [$fm, 'delete']);

            $db = new DatabaseManagementController($container->get(DatabaseRepository::class));
            $g->get('/database_management',          [$db, 'index']);
            $g->post('/database_management',         [$db, 'create']);
            $g->patch('/database_management/{id:\d+}', [$db, 'update']);
            $g->delete('/database_management/{id:\d+}', [$db, 'delete']);

            $bk = new BackupController($container->get(BackupRepository::class));
            $g->get('/backup_and_restore',            [$bk, 'index']);
            $g->post('/backup_and_restore',           [$bk, 'create']);
            $g->post('/backup_and_restore/upload',    [$bk, 'upload']);
            $g->post('/backup_and_restore/{id:\d+}/restore', [$bk, 'restore']);
            $g->delete('/backup_and_restore/{id:\d+}',[$bk, 'delete']);

            $ssl = new SslController($container->get(SslRepository::class), $container->get(DomainRepository::class));
            $g->get('/ssl_certificate_management',   [$ssl, 'index']);
            $g->post('/ssl_certificate_management',  [$ssl, 'create']);
            $g->post('/ssl_certificate_management/{id:\d+}/renew',  [$ssl, 'renew']);
            $g->post('/ssl_certificate_management/{id:\d+}/revoke', [$ssl, 'revoke']);

            $cron = new CronController($container->get(CronRepository::class));
            $g->get('/scheduled_tasks',          [$cron, 'index']);
            $g->post('/scheduled_tasks',         [$cron, 'create']);
            $g->patch('/scheduled_tasks/{id:\d+}', [$cron, 'update']);
            $g->post('/scheduled_tasks/{id:\d+}/run', [$cron, 'run']);
            $g->delete('/scheduled_tasks/{id:\d+}', [$cron, 'delete']);

            $ru = new ResourceController($container->get(ResourceRepository::class));
            $g->get('/resource_usage',          [$ru, 'index']);
            $g->post('/resource_usage',         [$ru, 'record']);
            $g->patch('/resource_usage/{id:\d+}', [$ru, 'update']);

            $tk = new TicketController($container->get(TicketRepository::class));
            $g->get('/support_tickets',          [$tk, 'index']);
            $g->post('/support_tickets',         [$tk, 'create']);
            $g->get('/support_tickets/{id:\d+}', [$tk, 'show']);
            $g->post('/support_tickets/{id:\d+}/reply',  [$tk, 'reply']);
            $g->patch('/support_tickets/{id:\d+}', [$tk, 'setStatus']);
            $g->post('/support_tickets/{id:\d+}/assign', [$tk, 'assign']);

            $au = new AuditController($container->get(AuditRepository::class));
            $g->get('/audit_logs',          [$au, 'index']);
            $g->post('/audit_logs',         [$au, 'create']);
            $g->get('/audit_logs/{id:\d+}', [$au, 'show']);
        });

        // Admin API endpoints - require admin role
        $adminApiGroup = $app->group('/api/host/admin_operations', function ($g) use ($container) {
            $admin = new AdminController(
                $container->get(UserRepository::class),
                $container->get(PlanRepository::class),
                $container->get(SettingsRepository::class),
            );
            $g->get('',                       [$admin, 'index']);
            $g->post('/users',                [$admin, 'createUser']);
            $g->patch('/users/{id:\d+}',      [$admin, 'updateUser']);
            $g->post('/users/{id:\d+}/update',[$admin, 'updateUser']);
            $g->post('/settings',             [$admin, 'updateSetting']);
        });
        $adminApiGroup->add(new RoleMiddleware(['admin']));

        return $app;
    }
}