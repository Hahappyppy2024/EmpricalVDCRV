<?php
declare(strict_types=1);
namespace App\Controller;

use App\Service\AccountService;
use App\Service\AdminService;
use App\Service\DownloadService;
use App\Service\FolderService;
use App\Service\FileService;
use App\Service\QuotaService;
use App\Service\AuditService;
use App\Service\SearchService;
use App\Service\ShareService;
use App\Service\TeamService;
use App\Service\TrashService;
use App\Service\VersionService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ApiController
{
    public function __construct(
        private AccountService $account,
        private FolderService $folder,
        private FileService $file,
        private ShareService $share,
        private TeamService $team,
        private SearchService $search,
        private VersionService $version,
        private TrashService $trash,
        private QuotaService $quota,
        private AuditService $audit,
        private AdminService $admin,
        private DownloadService $download
    ) {
    }

    public function handle(ServerRequestInterface $req, ResponseInterface $res, array $args): ResponseInterface
    {
        $useCase = (string)($args['case'] ?? '');
        $id = isset($args['id']) ? (int)$args['id'] : null;
        $method = strtoupper($req->getMethod());
        return match ($useCase) {
            'account_access' => $this->account->handle($req, $res, $method, $id),
            'folder_management' => $this->folder->handle($req, $res, $method, $id),
            'file_upload' => $this->file->handle($req, $res, $method, $id),
            'file_download_and_preview' => $this->download->handle($req, $res, $method, $id),
            'sharing_links' => $this->share->handle($req, $res, $method, $id),
            'team_spaces' => $this->team->handle($req, $res, $method, $id),
            'search' => $this->search->handle($req, $res, $method, $id),
            'version_history' => $this->version->handle($req, $res, $method, $id),
            'trash_and_restore' => $this->trash->handle($req, $res, $method, $id),
            'storage_quota' => $this->quota->handle($req, $res, $method, $id),
            'audit_log_and_exports' => $this->audit->handle($req, $res, $method, $id),
            'admin_console' => $this->admin->handle($req, $res, $method, $id),
            default => throw new \App\Infrastructure\HttpException(404, 'unknown_use_case'),
        };
    }
}