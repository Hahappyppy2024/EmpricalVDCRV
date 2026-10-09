<?php
declare(strict_types=1);

namespace MailServer;

use MailServer\Auth\AuthMiddleware;
use MailServer\Auth\SessionService;
use MailServer\Controllers\AccountAccessController;
use MailServer\Controllers\AdminAuditLogsController;
use MailServer\Controllers\AttachmentHandlingController;
use MailServer\Controllers\ContactManagementController;
use MailServer\Controllers\DomainManagementController;
use MailServer\Controllers\FiltersAndRulesController;
use MailServer\Controllers\FrontendApiIntegrationController;
use MailServer\Controllers\ImportExportController;
use MailServer\Controllers\MailboxOverviewController;
use MailServer\Controllers\MessageComposeController;
use MailServer\Controllers\MessageReadingController;
use MailServer\Controllers\QuarantineController;
use MailServer\Database\Database;
use MailServer\Database\Migrations;
use MailServer\Database\Seed;
use MailServer\Helpers\ResponseHelper;
use MailServer\Repositories\ApiErrorRepository;
use MailServer\Repositories\AuditRepository;
use MailServer\Repositories\ContactRepository;
use MailServer\Repositories\DomainRepository;
use MailServer\Repositories\FileRepository;
use MailServer\Repositories\MailboxRepository;
use MailServer\Repositories\MessageRepository;
use MailServer\Repositories\QuarantineRepository;
use MailServer\Repositories\RuleRepository;
use MailServer\Repositories\UserRepository;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Views\PhpRenderer;
use DI\Container;

class Application
{
    public static function boot(): App
    {
        if (file_exists(__DIR__ . '/../.env')) {
            $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
            $dotenv->safeLoad();
        }

        Database::connection();
        Migrations::run();
        Seed::run();

        $app = AppFactory::create(null, new Container());
        $app->addRoutingMiddleware();
        $app->addBodyParsingMiddleware();

        $errorMiddleware = $app->addErrorMiddleware(true, true, true);
        $errorMiddleware->setDefaultErrorHandler(function ($request, $exception) use ($app) {
            $response = $app->getResponseFactory()->createResponse();
            $path = $request->getUri()->getPath();
            if (str_starts_with($path, '/api/')) {
                return ResponseHelper::stableError($response, 'server_error', 500);
            }
            return ResponseHelper::html($response, '<h1>500 Server Error</h1><p>Internal application error.</p>', 500);
        });

        self::registerRoutes($app);
        return $app;
    }

    private static function registerRoutes(App $app): void
    {
        $container = $app->getContainer();
        $uploadDir = getenv('APP_UPLOAD_DIR') ?: (__DIR__ . '/../storage/uploads');
        $exportDir = getenv('APP_EXPORT_DIR') ?: (__DIR__ . '/../storage/exports');

        $session = new SessionService();
        $userRepo = new UserRepository();
        $mailboxRepo = new MailboxRepository();
        $messageRepo = new MessageRepository();
        $contactRepo = new ContactRepository();
        $ruleRepo = new RuleRepository();
        $domainRepo = new DomainRepository();
        $quarantineRepo = new QuarantineRepository();
        $auditRepo = new AuditRepository();
        $fileRepo = new FileRepository();
        $apiErrorRepo = new ApiErrorRepository();

        $renderer = new PhpRenderer(__DIR__ . '/../views');
        $renderer->setLayout('layout.php');
        $renderer->addAttribute('appName', 'Mail Server / Admin Console');
        $renderer->addAttribute('session', $session);
        if ($container instanceof \DI\Container) {
            $container->set('renderer', $renderer);
        }

        $accountCtl = new AccountAccessController($session, $userRepo, $renderer);
        $mailboxCtl = new MailboxOverviewController($mailboxRepo, $renderer);
        $composeCtl = new MessageComposeController($messageRepo, $contactRepo, $session, $renderer);
        $readingCtl = new MessageReadingController($messageRepo, $fileRepo, $session, $renderer);
        $attachCtl = new AttachmentHandlingController($fileRepo, $messageRepo, $session, $renderer, $uploadDir);
        $contactCtl = new ContactManagementController($contactRepo, $session, $renderer);
        $ruleCtl = new FiltersAndRulesController($ruleRepo, $session, $renderer);
        $domainCtl = new DomainManagementController($domainRepo, $userRepo, $session, $renderer);
        $quarantineCtl = new QuarantineController($quarantineRepo, $domainRepo, $session, $renderer);
        $auditCtl = new AdminAuditLogsController($auditRepo, $session, $renderer);
        $ieCtl = new ImportExportController($fileRepo, $contactRepo, $session, $renderer, $exportDir);
        $frontendCtl = new FrontendApiIntegrationController($apiErrorRepo, $auditRepo, $session, $renderer);

        $authAny = new AuthMiddleware($session, ['mail_user', 'domain_admin', 'system_admin']);
        $authMail = new AuthMiddleware($session, ['mail_user', 'domain_admin', 'system_admin']);
        $authDomainAdmin = new AuthMiddleware($session, ['domain_admin', 'system_admin']);
        $authSystem = new AuthMiddleware($session, ['system_admin']);

        $app->get('/', function ($request, $response) {
            $session = new SessionService();
            $session->start();
            if ($session->currentUserId()) {
                return $response->withHeader('Location', '/dashboard')->withStatus(302);
            }
            return $response->withHeader('Location', '/login')->withStatus(302);
        });

        $app->get('/login', [$accountCtl, 'showLogin']);
        $app->post('/login', [$accountCtl, 'login']);
        $app->get('/register', [$accountCtl, 'showRegister']);
        $app->post('/register', [$accountCtl, 'register']);
        $app->any('/logout', [$accountCtl, 'logout']);

        $app->group('', function ($g) use ($accountCtl, $mailboxCtl, $composeCtl, $readingCtl, $attachCtl, $contactCtl, $ruleCtl, $domainCtl, $quarantineCtl, $auditCtl, $ieCtl, $frontendCtl) {
            $g->get('/dashboard', [$accountCtl, 'showDashboard']);
            $g->get('/mail', [$mailboxCtl, 'show']);
            $g->get('/mail/compose', [$composeCtl, 'show']);
            $g->post('/mail/compose', [$composeCtl, 'send']);
            $g->get('/mail/message/{id:\d+}', [$readingCtl, 'show']);
            $g->get('/mail/attachments', [$attachCtl, 'show']);
            $g->post('/mail/attachments', [$attachCtl, 'upload']);
            $g->get('/mail/attachments/{id:\d+}/download', [$attachCtl, 'download']);
            $g->get('/mail/contacts', [$contactCtl, 'show']);
            $g->post('/mail/contacts', [$contactCtl, 'create']);
            $g->post('/mail/contacts/{id:\d+}/edit', [$contactCtl, 'edit']);
            $g->post('/mail/contacts/{id:\d+}/delete', [$contactCtl, 'delete']);
            $g->get('/mail/rules', [$ruleCtl, 'show']);
            $g->post('/mail/rules', [$ruleCtl, 'create']);
            $g->post('/mail/rules/{id:\d+}/edit', [$ruleCtl, 'update']);
            $g->post('/mail/rules/{id:\d+}/delete', [$ruleCtl, 'delete']);
            $g->get('/mail/domains', [$domainCtl, 'show']);
            $g->post('/mail/domains', [$domainCtl, 'create']);
            $g->post('/mail/domains/{id:\d+}/edit', [$domainCtl, 'update']);
            $g->post('/mail/domains/{id:\d+}/aliases', [$domainCtl, 'createAlias']);
            $g->get('/mail/quarantine', [$quarantineCtl, 'show']);
            $g->post('/mail/quarantine/{id:\d+}/resolve', [$quarantineCtl, 'resolve']);
            $g->get('/mail/audit', [$auditCtl, 'show']);
            $g->get('/mail/import-export', [$ieCtl, 'show']);
            $g->post('/mail/import-export/import', [$ieCtl, 'importContacts']);
            $g->get('/mail/import-export/export', [$ieCtl, 'exportContacts']);
            $g->get('/mail/import-export/{id:\d+}/download', [$ieCtl, 'download']);
            $g->get('/mail/api-errors', [$frontendCtl, 'show']);
            $g->post('/mail/api-errors', [$frontendCtl, 'simulate']);
        })->add($authAny);

        $app->group('', function ($g) use ($domainCtl, $quarantineCtl, $auditCtl) {
            $g->map(['GET', 'POST'], '/api/mail/domain_management', [$domainCtl, 'apiPost']);
            $g->map(['GET', 'POST'], '/api/mail/domain_management/', [$domainCtl, 'apiPost']);
            $g->patch('/api/mail/domain_management/{id:\d+}', [$domainCtl, 'apiPatch']);
            $g->get('/api/mail/domain_management/list', [$domainCtl, 'apiGet']);
        })->add($authDomainAdmin);

        $app->group('', function ($g) use ($quarantineCtl) {
            $g->get('/api/mail/quarantine', [$quarantineCtl, 'apiGet']);
            $g->post('/api/mail/quarantine', [$quarantineCtl, 'apiPost']);
            $g->patch('/api/mail/quarantine/{id:\d+}', [$quarantineCtl, 'apiPatch']);
        })->add($authDomainAdmin);

        $app->group('', function ($g) use ($auditCtl) {
            $g->get('/api/mail/admin_audit_logs', [$auditCtl, 'apiGet']);
            $g->post('/api/mail/admin_audit_logs', [$auditCtl, 'apiPost']);
            $g->patch('/api/mail/admin_audit_logs/{id:\d+}', [$auditCtl, 'apiPatch']);
        })->add($authSystem);

        $app->group('', function ($g) use ($accountCtl, $mailboxCtl, $composeCtl, $readingCtl, $attachCtl, $contactCtl, $ruleCtl, $ieCtl, $frontendCtl) {
            $g->get('/api/mail/account_access', [$accountCtl, 'apiGet']);
            $g->post('/api/mail/account_access', [$accountCtl, 'apiPost']);
            $g->patch('/api/mail/account_access/{id:\d+}', [$accountCtl, 'apiPatch']);
            $g->get('/api/mail/mailbox_overview', [$mailboxCtl, 'apiGet']);
            $g->post('/api/mail/mailbox_overview', [$mailboxCtl, 'apiPost']);
            $g->patch('/api/mail/mailbox_overview/{id:\d+}', [$mailboxCtl, 'apiPatch']);
            $g->get('/api/mail/message_compose', [$composeCtl, 'apiGet']);
            $g->post('/api/mail/message_compose', [$composeCtl, 'apiPost']);
            $g->patch('/api/mail/message_compose/{id:\d+}', [$composeCtl, 'apiPatch']);
            $g->get('/api/mail/message_reading', [$readingCtl, 'apiGet']);
            $g->post('/api/mail/message_reading', [$readingCtl, 'apiPost']);
            $g->patch('/api/mail/message_reading/{id:\d+}', [$readingCtl, 'apiPatch']);
            $g->get('/api/mail/attachment_handling', [$attachCtl, 'apiGet']);
            $g->post('/api/mail/attachment_handling', [$attachCtl, 'apiPost']);
            $g->patch('/api/mail/attachment_handling/{id:\d+}', [$attachCtl, 'apiPatch']);
            $g->get('/api/mail/contact_management', [$contactCtl, 'apiGet']);
            $g->post('/api/mail/contact_management', [$contactCtl, 'apiPost']);
            $g->patch('/api/mail/contact_management/{id:\d+}', [$contactCtl, 'apiPatch']);
            $g->get('/api/mail/filters_and_rules', [$ruleCtl, 'apiGet']);
            $g->post('/api/mail/filters_and_rules', [$ruleCtl, 'apiPost']);
            $g->patch('/api/mail/filters_and_rules/{id:\d+}', [$ruleCtl, 'apiPatch']);
            $g->get('/api/mail/import_export', [$ieCtl, 'apiGet']);
            $g->post('/api/mail/import_export', [$ieCtl, 'apiPost']);
            $g->patch('/api/mail/import_export/{id:\d+}', [$ieCtl, 'apiPatch']);
            $g->get('/api/mail/frontend_api_integration_and_errors', [$frontendCtl, 'apiGet']);
            $g->post('/api/mail/frontend_api_integration_and_errors', [$frontendCtl, 'apiPost']);
            $g->patch('/api/mail/frontend_api_integration_and_errors/{id:\d+}', [$frontendCtl, 'apiPatch']);
        })->add($authMail);

        $app->get('/health', function ($request, $response) {
            $response->getBody()->write(json_encode(['status' => 'ok', 'app' => 'mail-server-admin-console']));
            return $response->withHeader('Content-Type', 'application/json');
        });

        $app->get('/assets/{path:.*}', function ($request, $response, array $args) {
            $path = __DIR__ . '/../public/' . $args['path'];
            $real = realpath($path);
            $base = realpath(__DIR__ . '/../public');
            if (!$real || !$base || !str_starts_with($real, $base) || !is_file($real)) {
                return $response->withStatus(404);
            }
            $ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
            $type = match ($ext) {
                'css' => 'text/css',
                'js' => 'application/javascript',
                'json' => 'application/json',
                'png' => 'image/png',
                'jpg', 'jpeg' => 'image/jpeg',
                'svg' => 'image/svg+xml',
                default => 'application/octet-stream',
            };
            return $response->withHeader('Content-Type', $type)->withBody(new \Slim\Psr7\Stream(fopen($real, 'rb')));
        });
    }
}