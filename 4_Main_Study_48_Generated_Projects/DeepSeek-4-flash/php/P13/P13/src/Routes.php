<?php

declare(strict_types=1);

namespace P13;

use P13\Controllers\AccountAccessController;
use P13\Controllers\AdminAuditLogsController;
use P13\Controllers\AttachmentHandlingController;
use P13\Controllers\AuthController;
use P13\Controllers\ContactManagementController;
use P13\Controllers\DomainManagementController;
use P13\Controllers\FiltersAndRulesController;
use P13\Controllers\FrontendApiErrorsController;
use P13\Controllers\ImportExportController;
use P13\Controllers\MailboxOverviewController;
use P13\Controllers\MessageComposeController;
use P13\Controllers\MessageReadingController;
use P13\Controllers\PageController;
use P13\Controllers\QuarantineController;
use P13\Middleware\AuthMiddleware;
use P13\Middleware\RoleMiddleware;
use Slim\App as SlimApp;

/**
 * All HTTP routes. API paths match the contract exactly:
 *   /api/mail/{module}
 *   /api/mail/{module}/{id}
 */
final class Routes
{
    public static function register(SlimApp $app): void
    {
        $container = $app->getContainer();
        $session = $container->get(Session::class);

        // ---- Health -----------------------------------------------------
        $app->get('/api/health', function ($request, $response) {
            return ok_response($response, [
                'status' => 'ok',
                'time' => now_iso(),
                'app' => 'P13 Mail Server / Admin Console',
            ]);
        });

        // ---- Auth pages & API -------------------------------------------
        $auth = $container->get(AuthController::class);
        $app->get('/', [PageController::class, 'home']);
        $app->get('/login', [$auth, 'showLogin']);
        $app->post('/login', [$auth, 'postLogin']);
        $app->get('/register', [$auth, 'showRegister']);
        $app->post('/register', [$auth, 'postRegister']);
        $app->post('/logout', [$auth, 'postLogout']);
        $app->get('/api/auth/me', [$auth, 'me']);
        $app->post('/api/auth/login', [$auth, 'apiLogin']);
        $app->post('/api/auth/register', [$auth, 'apiRegister']);
        $app->post('/api/auth/logout', [$auth, 'apiLogout']);

        // ---- Authenticated browser pages ----------------------------------
        $app->group('', function (\Slim\Routing\RouteCollectorProxy $group) use ($container) {
            $pages = $container->get(PageController::class);
            $group->get('/dashboard', [$pages, 'dashboard']);
            $group->get('/account', [$pages, 'account']);
            $group->get('/mailbox', [$pages, 'mailbox']);
            $group->get('/compose', [$pages, 'compose']);
            $group->get('/compose/{id:\d+}', [$pages, 'compose']);
            $group->get('/message/{id:\d+}', [$pages, 'message']);
            $group->get('/attachments', [$pages, 'attachments']);
            $group->get('/contacts', [$pages, 'contacts']);
            $group->get('/rules', [$pages, 'rules']);
            $group->get('/import-export', [$pages, 'importExport']);
            $group->get('/errors', [$pages, 'errorsDemo']);
        })->add(new AuthMiddleware($session));

        $app->group('', function (\Slim\Routing\RouteCollectorProxy $group) use ($container) {
            $pages = $container->get(PageController::class);
            $group->get('/domains', [$pages, 'domains']);
            $group->get('/quarantine', [$pages, 'quarantine']);
        })
            ->add(new RoleMiddleware($session, ['domain_admin', 'system_admin']))
            ->add(new AuthMiddleware($session));

        $app->group('', function (\Slim\Routing\RouteCollectorProxy $group) use ($container) {
            $pages = $container->get(PageController::class);
            $group->get('/audit', [$pages, 'audit']);
        })
            ->add(new RoleMiddleware($session, ['system_admin']))
            ->add(new AuthMiddleware($session));

        // ---- Authenticated module APIs ------------------------------------
        self::apiGroup($app, $container, 'account_access', AccountAccessController::class, []);
        self::apiGroup($app, $container, 'mailbox_overview', MailboxOverviewController::class, []);
        self::apiGroup($app, $container, 'message_compose', MessageComposeController::class, []);
        self::apiGroup($app, $container, 'message_reading', MessageReadingController::class, [], true);
        self::apiGroup($app, $container, 'attachment_handling', AttachmentHandlingController::class, [], false, true);
        self::apiGroup($app, $container, 'contact_management', ContactManagementController::class, []);
        self::apiGroup($app, $container, 'filters_and_rules', FiltersAndRulesController::class, []);
        self::apiGroup($app, $container, 'domain_management', DomainManagementController::class, ['domain_admin', 'system_admin']);
        self::apiGroup($app, $container, 'quarantine', QuarantineController::class, ['domain_admin', 'system_admin']);
        self::apiGroup($app, $container, 'admin_audit_logs', AdminAuditLogsController::class, ['system_admin']);
        self::apiGroup($app, $container, 'import_export', ImportExportController::class, []);
        self::apiGroup($app, $container, 'frontend_api_integration_and_errors', FrontendApiErrorsController::class, []);
    }

    /**
     * Register GET/POST/PATCH for a module with optional show/download routes.
     *
     * @param string[] $roles
     */
    private static function apiGroup(
        SlimApp $app,
        \P13\AppContainer $container,
        string $module,
        string $controllerClass,
        array $roles,
        bool $withShow = false,
        bool $withDownload = false
    ): void {
        $session = $container->get(Session::class);
        $group = $app->group('/api/mail/' . $module, function (\Slim\Routing\RouteCollectorProxy $group) use ($controllerClass, $withShow, $withDownload) {
            $group->get('', [$controllerClass, 'index']);
            $group->post('', [$controllerClass, 'create']);
            $group->patch('/{id:\d+}', [$controllerClass, 'update']);
            if ($withShow) {
                $group->get('/{id:\d+}', [$controllerClass, 'show']);
            }
            if ($withDownload) {
                $group->get('/{id:\d+}/download', [$controllerClass, 'download']);
            }
        });
        if ($roles !== []) {
            $group->add(new RoleMiddleware($session, $roles));
        }
        $group->add(new AuthMiddleware($session));
    }
}
