<?php
declare(strict_types=1);
namespace App\Bootstrap;

use App\Controller\ApiController;
use App\Controller\WebController;
use App\Middleware\ApiExceptionMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\SessionMiddleware;
use App\Repository\AccountRepository;
use App\Repository\AdminRepository;
use App\Repository\AuditRepository;
use App\Repository\FileRepository;
use App\Repository\FolderLogRepository;
use App\Repository\FolderRepository;
use App\Repository\QuotaRepository;
use App\Repository\SearchRepository;
use App\Repository\ShareRepository;
use App\Repository\TeamRepository;
use App\Repository\TrashRepository;
use App\Repository\UserRepository;
use App\Repository\VersionLogRepository;
use App\Service\AccountService;
use App\Service\AdminService;
use App\Service\AuditService;
use App\Service\DownloadService;
use App\Service\FileService;
use App\Service\FolderService;
use App\Service\QuotaService;
use App\Service\SearchService;
use App\Service\ShareService;
use App\Service\TeamService;
use App\Service\TrashService;
use App\Service\VersionService;
use DI\Container;
use Psr\Http\Message\ResponseFactoryInterface;
use Slim\Psr7\Factory\ResponseFactory;

final class AppFactory
{
    public static function create(): \Slim\App
    {
        $responseFactory = new ResponseFactory();
        \Slim\Factory\AppFactory::setResponseFactory($responseFactory);
        $app = \Slim\Factory\AppFactory::create();
        $app->addRoutingMiddleware();
        $app->addBodyParsingMiddleware();
        $app->add(new ApiExceptionMiddleware());
        $app->add(new CsrfMiddleware());
        $app->add(new SessionMiddleware());
        $app->addErrorMiddleware(true, true, true);
        self::registerRoutes($app);
        return $app;
    }

    private static function registerRoutes(\Slim\App $app): void
    {
        $web = new WebController();
        $container = $app->getContainer();

        $app->get('/', [$web, 'home']);
        $app->get('/login', [$web, 'login']);
        $app->get('/register', [$web, 'register']);
        $app->get('/recover', [$web, 'recover']);
        $app->get('/dashboard', [$web, 'dashboard']);
        $app->get('/upload', [$web, 'upload']);
        $app->get('/folders', [$web, 'folders']);
        $app->get('/files', [$web, 'files']);
        $app->get('/files/{id}', [$web, 'files']);
        $app->get('/files/{id}/download', [$web, 'download']);
        $app->get('/files/{id}/preview', [$web, 'preview']);
        $app->get('/files/{id}/versions', [$web, 'files']);
        $app->get('/shares', [$web, 'shares']);
        $app->get('/teams', [$web, 'teams']);
        $app->get('/search', [$web, 'search']);
        $app->get('/trash', [$web, 'trash']);
        $app->get('/quota', [$web, 'quota']);
        $app->get('/activity', [$web, 'audit']);
        $app->get('/admin', [$web, 'admin']);
        $app->get('/s/{token}', [$web, 'sharePage']);
        $app->get('/s/{token}/download', [$web, 'shareDownload']);
        $app->get('/s/{token}/preview', [$web, 'sharePreview']);
        $app->get('/audit/{id}/download', [$web, 'downloadExport']);

        $controller = self::apiController();
        $cases = [
            'account_access',
            'folder_management',
            'file_upload',
            'file_download_and_preview',
            'sharing_links',
            'team_spaces',
            'search',
            'version_history',
            'trash_and_restore',
            'storage_quota',
            'audit_log_and_exports',
            'admin_console',
        ];
        foreach ($cases as $case) {
            $app->get('/api/file/' . $case, function ($req, $res) use ($controller, $case) {
                return $controller->handle($req, $res, ['case' => $case]);
            });
            $app->post('/api/file/' . $case, function ($req, $res) use ($controller, $case) {
                return $controller->handle($req, $res, ['case' => $case]);
            });
            $app->patch('/api/file/' . $case . '/{id}', function ($req, $res, $args) use ($controller, $case) {
                $args['case'] = $case;
                return $controller->handle($req, $res, $args);
            });
        }
    }

    private static function apiController(): ApiController
    {
        $users = new UserRepository();
        $folders = new FolderRepository();
        $files = new FileRepository();
        $teams = new TeamRepository();
        $shares = new ShareRepository();
        $searches = new SearchRepository();
        $audit = new AuditRepository();
        $quotas = new QuotaRepository();
        $admin = new AdminRepository();
        $trash = new TrashRepository();
        $accountLog = new AccountRepository();
        $folderLog = new FolderLogRepository();
        $versionLog = new VersionLogRepository();

        $account = new AccountService($users, $folders, $quotas, $accountLog);
        $folder = new FolderService($folders, $files, $trash, $folderLog, $teams);
        $file = new FileService($files, $folders, $quotas, $teams, $admin, $trash);
        $share = new ShareService($shares, $files, $teams);
        $team = new TeamService($teams, $folders, $files, $quotas);
        $search = new SearchService($files, $searches, $teams);
        $version = new VersionService($files, $teams, $quotas, $versionLog);
        $trashSvc = new TrashService($trash, $files, $folders, $teams, $quotas);
        $quotaSvc = new QuotaService($quotas, $users, $teams);
        $auditSvc = new AuditService($audit, $users, $files, $admin);
        $adminSvc = new AdminService($admin, $users, $quotas, $teams, $folders);
        $download = new DownloadService($files, $shares, $teams);

        return new ApiController($account, $folder, $file, $share, $team, $search, $version, $trashSvc, $quotaSvc, $auditSvc, $adminSvc, $download);
    }
}