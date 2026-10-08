<?php

declare(strict_types=1);

namespace CloudFS;

use CloudFS\Controllers\AccountAccessController;
use CloudFS\Controllers\AdminConsoleController;
use CloudFS\Controllers\AuthController;
use CloudFS\Controllers\AuditController;
use CloudFS\Controllers\DashboardController;
use CloudFS\Controllers\DownloadPreviewController;
use CloudFS\Controllers\FileUploadController;
use CloudFS\Controllers\FolderManagementController;
use CloudFS\Controllers\QuotaController;
use CloudFS\Controllers\SearchController;
use CloudFS\Controllers\SharingLinksController;
use CloudFS\Controllers\TeamSpacesController;
use CloudFS\Controllers\TrashController;
use CloudFS\Controllers\VersionHistoryController;
use CloudFS\Database\Database;
use CloudFS\Middleware\RequireAdminMiddleware;
use CloudFS\Middleware\RequireAuthMiddleware;
use CloudFS\Middleware\SessionMiddleware;
use CloudFS\Repositories\FileRepository;
use CloudFS\Repositories\SettingsRepository;
use CloudFS\Repositories\TeamRepository;
use CloudFS\Repositories\UserRepository;
use CloudFS\Services\AuditService;
use CloudFS\Services\AuthService;
use CloudFS\Services\QuotaService;
use CloudFS\Services\RealtimeService;
use CloudFS\Services\SessionService;
use CloudFS\Services\StorageService;
use CloudFS\Services\ValidationService;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Views\PhpRenderer;

final class App
{
    public static function create(array $config): \Slim\App
    {
        $dbPath = $config['db_path'];
        $db = new Database($dbPath);

        $storage = new StorageService($config['storage_path']);
        $validation = new ValidationService();
        $sessions = new SessionService($db, $config['session_cookie'], (int) $config['session_lifetime']);
        $audit = new AuditService($db);
        $realtime = new RealtimeService($db);
        $quota = new QuotaService($db);
        $auth = new AuthService($db, $sessions, $validation);

        $users = new UserRepository($db);
        $files = new FileRepository($db);
        $teams = new TeamRepository($db);
        $settings = new SettingsRepository($db);

        $view = new PhpRenderer(dirname(__DIR__) . '/src/Views');
        $view->setAttributes([
            'app_name' => $config['app_name'],
            'ws_port' => (int) $config['ws_port'],
            'current_user' => null,
        ]);

        $app = AppFactory::create(new ResponseFactory());
        $app->add(new SessionMiddleware($sessions));
        $app->addRoutingMiddleware();
        $errorMiddleware = $app->addErrorMiddleware(false, true, true);
        $errorMiddleware->setDefaultErrorHandler(function ($request, \Throwable $exception) {
            error_log('CLOUDFS ERROR: ' . $exception->getMessage() . ' @ ' . $exception->getFile() . ':' . $exception->getLine() . "\n" . $exception->getTraceAsString());
            $response = new \Slim\Psr7\Response(500);
            $response->getBody()->write(json_encode(['ok' => false, 'error' => 'An unexpected error occurred.']));
            return $response->withHeader('Content-Type', 'application/json');
        });

        $requireAuth = new RequireAuthMiddleware();
        $requireAdmin = new RequireAdminMiddleware();

        $controllers = [
            'auth' => new AuthController($auth, $sessions, $view, $config),
            'dashboard' => new DashboardController($files, $users, $view, $audit),
            'account_access' => new AccountAccessController($db, $view, $audit),
            'upload' => new FileUploadController($db, $files, $storage, $quota, $settings, $validation, $view, $audit, $realtime),
            'folders' => new FolderManagementController($db, $files, $view, $audit),
            'download' => new DownloadPreviewController($db, $files, $storage, $view, $audit),
            'sharing' => new SharingLinksController($db, $files, $view, $audit),
            'teams' => new TeamSpacesController($db, $users, $teams, $files, $view, $audit),
            'search' => new SearchController($db, $view),
            'versions' => new VersionHistoryController($db, $files, $storage, $view, $audit, $realtime),
            'trash' => new TrashController($db, $files, $storage, $view, $audit),
            'quota' => new QuotaController($db, $users, $quota, $view, $audit),
            'audit' => new AuditController($db, $audit, $view),
            'admin' => new AdminConsoleController($db, $users, $quota, $settings, $view, $audit, $realtime),
        ];

        // Guest / account routes (FILE-01)
        $app->get('/', [$controllers['dashboard'], 'index']);
        $app->get('/register', [$controllers['auth'], 'registerForm']);
        $app->post('/register', [$controllers['auth'], 'register']);
        $app->get('/login', [$controllers['auth'], 'loginForm']);
        $app->post('/login', [$controllers['auth'], 'login']);
        $app->get('/reset', [$controllers['auth'], 'resetForm']);
        $app->post('/reset', [$controllers['auth'], 'resetRequest']);
        $app->get('/api/health', function ($request, $response) use ($db) {
            $db->run('SELECT 1');
            $payload = ['ok' => true, 'app' => 'cloud-file-sharing-system', 'database' => 'sqlite', 'status' => 'healthy'];
            $response->getBody()->write(json_encode($payload));
            return $response->withHeader('Content-Type', 'application/json');
        });

        // Authenticated routes (all 12 use cases)
        $app->group('', function ($group) use ($controllers) {
            $group->post('/logout', [$controllers['auth'], 'logout']);
            $group->get('/dashboard', [$controllers['dashboard'], 'index']);
            $group->get('/files', [$controllers['dashboard'], 'index']);

            $group->get('/account-access', [$controllers['account_access'], 'page']);
            $group->get('/upload', [$controllers['upload'], 'page']);
            $group->get('/folders', [$controllers['folders'], 'page']);
            $group->get('/sharing', [$controllers['sharing'], 'page']);
            $group->get('/teams', [$controllers['teams'], 'page']);
            $group->get('/search', [$controllers['search'], 'page']);
            $group->get('/versions', [$controllers['versions'], 'page']);
            $group->get('/trash', [$controllers['trash'], 'page']);
            $group->get('/quota', [$controllers['quota'], 'page']);
            $group->get('/audit', [$controllers['audit'], 'page']);

            $group->get('/files/{id}/download', [$controllers['download'], 'download']);
            $group->get('/files/{id}/preview', [$controllers['download'], 'preview']);

            // FILE-01
            $group->get('/api/file/account_access', [$controllers['account_access'], 'index']);
            $group->post('/api/file/account_access', [$controllers['account_access'], 'create']);
            $group->patch('/api/file/account_access/{id}', [$controllers['account_access'], 'update']);
            // FILE-02
            $group->get('/api/file/file_upload', [$controllers['upload'], 'index']);
            $group->post('/api/file/file_upload', [$controllers['upload'], 'create']);
            $group->patch('/api/file/file_upload/{id}', [$controllers['upload'], 'update']);
            // FILE-03
            $group->get('/api/file/folder_management', [$controllers['folders'], 'index']);
            $group->post('/api/file/folder_management', [$controllers['folders'], 'create']);
            $group->patch('/api/file/folder_management/{id}', [$controllers['folders'], 'update']);
            // FILE-04
            $group->get('/api/file/file_download_and_preview', [$controllers['download'], 'index']);
            $group->post('/api/file/file_download_and_preview', [$controllers['download'], 'create']);
            $group->patch('/api/file/file_download_and_preview/{id}', [$controllers['download'], 'update']);
            // FILE-05
            $group->get('/api/file/sharing_links', [$controllers['sharing'], 'index']);
            $group->post('/api/file/sharing_links', [$controllers['sharing'], 'create']);
            $group->patch('/api/file/sharing_links/{id}', [$controllers['sharing'], 'update']);
            // FILE-06
            $group->get('/api/file/team_spaces', [$controllers['teams'], 'index']);
            $group->post('/api/file/team_spaces', [$controllers['teams'], 'create']);
            $group->patch('/api/file/team_spaces/{id}', [$controllers['teams'], 'update']);
            // FILE-07
            $group->get('/api/file/search', [$controllers['search'], 'index']);
            $group->post('/api/file/search', [$controllers['search'], 'create']);
            $group->patch('/api/file/search/{id}', [$controllers['search'], 'update']);
            // FILE-08
            $group->get('/api/file/version_history', [$controllers['versions'], 'index']);
            $group->post('/api/file/version_history', [$controllers['versions'], 'create']);
            $group->patch('/api/file/version_history/{id}', [$controllers['versions'], 'update']);
            // FILE-09
            $group->get('/api/file/trash_and_restore', [$controllers['trash'], 'index']);
            $group->post('/api/file/trash_and_restore', [$controllers['trash'], 'create']);
            $group->patch('/api/file/trash_and_restore/{id}', [$controllers['trash'], 'update']);
            // FILE-10
            $group->get('/api/file/storage_quota', [$controllers['quota'], 'index']);
            $group->post('/api/file/storage_quota', [$controllers['quota'], 'create']);
            $group->patch('/api/file/storage_quota/{id}', [$controllers['quota'], 'update']);
            // FILE-11
            $group->get('/api/file/audit_log_and_exports', [$controllers['audit'], 'index']);
            $group->post('/api/file/audit_log_and_exports', [$controllers['audit'], 'create']);
            $group->patch('/api/file/audit_log_and_exports/{id}', [$controllers['audit'], 'update']);
            $group->get('/api/file/audit_log_and_exports/export/{format}', [$controllers['audit'], 'exportFile']);
            // FILE-12
            $group->get('/api/file/admin_console', [$controllers['admin'], 'index']);
            $group->post('/api/file/admin_console', [$controllers['admin'], 'create']);
            $group->patch('/api/file/admin_console/{id}', [$controllers['admin'], 'update']);
        })->add($requireAuth);

        // Admin-only routes (FILE-06, FILE-12)
        $app->group('/admin', function ($group) use ($controllers) {
            $group->get('', [$controllers['admin'], 'page']);
            $group->post('/users/{id}/quota', [$controllers['admin'], 'setUserQuota']);
            $group->post('/policy', [$controllers['admin'], 'updatePolicy']);
            $group->post('/blocked', [$controllers['admin'], 'addBlocked']);
            $group->delete('/blocked/{extension}', [$controllers['admin'], 'removeBlocked']);
        })->add($requireAdmin);

        // Share recipient workflow (FILE-04 public access via token)
        $app->get('/s/{token}', [$controllers['download'], 'sharePage']);
        $app->get('/s/{token}/download', [$controllers['download'], 'shareDownload']);

        return $app;
    }
}
