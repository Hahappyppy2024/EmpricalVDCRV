<?php
declare(strict_types=1);
namespace App\Controller;

use App\Infrastructure\Config;
use App\Infrastructure\Csrf;
use App\Infrastructure\HttpException;
use App\Infrastructure\View;
use App\Repository\AdminRepository;
use App\Repository\AuditRepository;
use App\Repository\FileRepository;
use App\Repository\FolderRepository;
use App\Repository\QuotaRepository;
use App\Repository\SearchRepository;
use App\Repository\ShareRepository;
use App\Repository\TeamRepository;
use App\Repository\TrashRepository;
use App\Repository\UserRepository;
use App\Service\ShareService;
use App\Infrastructure\Storage;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;

final class WebController
{
    public function home(ServerRequestInterface $req, ResponseInterface $res): ResponseInterface
    {
        $user = $req->getAttribute('user');
        return View::render($res, 'home', $this->baseContext($user, $req));
    }

    public function login(ServerRequestInterface $req, ResponseInterface $res): ResponseInterface
    {
        $user = $req->getAttribute('user');
        return View::render($res, 'login', $this->baseContext($user, $req));
    }

    public function register(ServerRequestInterface $req, ResponseInterface $res): ResponseInterface
    {
        $user = $req->getAttribute('user');
        return View::render($res, 'register', $this->baseContext($user, $req));
    }

    public function recover(ServerRequestInterface $req, ResponseInterface $res): ResponseInterface
    {
        $user = $req->getAttribute('user');
        return View::render($res, 'recover', $this->baseContext($user, $req));
    }

    public function dashboard(ServerRequestInterface $req, ResponseInterface $res): ResponseInterface
    {
        $user = $this->requireUser($req, $res);
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $quotas = $this->quotasRepo();
        $teams = $this->teamsRepo()->listForUser((int)$user['user_id']);
        $files = $this->filesRepo();
        $quota = $quotas->getForUser((int)$user['user_id']) ?? ['limit_bytes' => 0, 'used_bytes' => 0];
        $recent = $files->listSharedWith((int)$user['user_id'], array_map(static fn($t) => (int)$t['id'], $teams));
        $recent = array_slice($recent, 0, 10);
        $folders = (new FolderRepository())->listAccessibleForUser((int)$user['user_id'], array_map(static fn($t) => (int)$t['id'], $teams));
        $data = $this->baseContext($user, $req);
        $data['quota'] = $quota;
        $data['teams'] = $teams;
        $data['recent'] = $recent;
        $data['folders'] = $folders;
        return View::render($res, 'dashboard', $data);
    }

    public function upload(ServerRequestInterface $req, ResponseInterface $res): ResponseInterface
    {
        $user = $this->requireUser($req, $res);
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $teams = $this->teamsRepo()->listForUser((int)$user['user_id']);
        $folders = (new FolderRepository())->listAccessibleForUser((int)$user['user_id'], array_map(static fn($t) => (int)$t['id'], $teams));
        $data = $this->baseContext($user, $req);
        $data['teams'] = $teams;
        $data['folders'] = $folders;
        return View::render($res, 'upload', $data);
    }

    public function folders(ServerRequestInterface $req, ResponseInterface $res): ResponseInterface
    {
        $user = $this->requireUser($req, $res);
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $teams = $this->teamsRepo()->listForUser((int)$user['user_id']);
        $folders = (new FolderRepository())->listAccessibleForUser((int)$user['user_id'], array_map(static fn($t) => (int)$t['id'], $teams));
        $data = $this->baseContext($user, $req);
        $data['folders'] = $folders;
        $data['teams'] = $teams;
        return View::render($res, 'folders', $data);
    }

    public function files(ServerRequestInterface $req, ResponseInterface $res, array $args): ResponseInterface
    {
        $user = $this->requireUser($req, $res);
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $fileId = isset($args['id']) ? (int)$args['id'] : null;
        $files = $this->filesRepo();
        $teams = $this->teamsRepo()->listForUser((int)$user['user_id']);
        $teamIds = array_map(static fn($t) => (int)$t['id'], $teams);
        if ($fileId) {
            $file = $files->find($fileId);
            if (!$file) {
                throw new HttpException(404, 'file_not_found');
            }
            if (!$this->canAccessFile($user, $file, $teamIds)) {
                throw new HttpException(403, 'file_forbidden');
            }
            $versions = $files->versions($fileId);
            $shares = (new ShareRepository())->listForOwner((int)$user['user_id']);
            $fileShares = array_values(array_filter($shares, fn($s) => (int)($s['file_id'] ?? 0) === $fileId));
            $data = $this->baseContext($user, $req);
            $data['file'] = $file;
            $data['versions'] = $versions;
            $data['file_tags'] = $files->getTags($fileId);
            $data['file_shares'] = $fileShares;
            return View::render($res, 'file_detail', $data);
        }
        $list = $files->listSharedWith((int)$user['user_id'], $teamIds);
        $data = $this->baseContext($user, $req);
        $data['files'] = $list;
        $data['teams'] = $teams;
        return View::render($res, 'files', $data);
    }

    public function shares(ServerRequestInterface $req, ResponseInterface $res): ResponseInterface
    {
        $user = $this->requireUser($req, $res);
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $shares = (new ShareRepository())->listForOwner((int)$user['user_id']);
        $incoming = (new ShareRepository())->listPrivateForUser((int)$user['user_id']);
        $data = $this->baseContext($user, $req);
        $data['shares'] = $shares;
        $data['incoming'] = $incoming;
        return View::render($res, 'shares', $data);
    }

    public function teams(ServerRequestInterface $req, ResponseInterface $res): ResponseInterface
    {
        $user = $this->requireUser($req, $res);
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $teams = $this->teamsRepo()->listForUser((int)$user['user_id']);
        if ($user['role'] === 'admin') {
            $teams = $this->teamsRepo()->listAll();
        }
        $data = $this->baseContext($user, $req);
        $data['teams'] = $teams;
        return View::render($res, 'teams', $data);
    }

    public function search(ServerRequestInterface $req, ResponseInterface $res): ResponseInterface
    {
        $user = $this->requireUser($req, $res);
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $data = $this->baseContext($user, $req);
        $data['saved'] = (new SearchRepository())->listForUser((int)$user['user_id']);
        return View::render($res, 'search', $data);
    }

    public function trash(ServerRequestInterface $req, ResponseInterface $res): ResponseInterface
    {
        $user = $this->requireUser($req, $res);
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $data = $this->baseContext($user, $req);
        $data['items'] = (new TrashRepository())->listActive();
        return View::render($res, 'trash', $data);
    }

    public function quota(ServerRequestInterface $req, ResponseInterface $res): ResponseInterface
    {
        $user = $this->requireUser($req, $res);
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $teams = $this->teamsRepo()->listForUser((int)$user['user_id']);
        $data = $this->baseContext($user, $req);
        $data['quota'] = $this->quotasRepo()->getForUser((int)$user['user_id']);
        $data['teams'] = $teams;
        return View::render($res, 'quota', $data);
    }

    public function audit(ServerRequestInterface $req, ResponseInterface $res): ResponseInterface
    {
        $user = $this->requireUser($req, $res);
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $isAdmin = $user['role'] === 'admin';
        $data = $this->baseContext($user, $req);
        $data['events'] = $isAdmin ? (new AuditRepository())->listEvents() : (new AuditRepository())->listForUser((int)$user['user_id']);
        $data['exports'] = $isAdmin ? (new AuditRepository())->listExportsAll() : (new AuditRepository())->listExportsForUser((int)$user['user_id']);
        return View::render($res, 'audit', $data);
    }

    public function admin(ServerRequestInterface $req, ResponseInterface $res): ResponseInterface
    {
        $user = $this->requireUser($req, $res);
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        if ($user['role'] !== 'admin') {
            throw new HttpException(403, 'admin_required');
        }
        $data = $this->baseContext($user, $req);
        $data['users'] = $this->usersRepo()->listAll();
        $data['settings'] = (new AdminRepository())->settings();
        $data['teams'] = $this->teamsRepo()->listAll();
        return View::render($res, 'admin', $data);
    }

    public function sharePage(ServerRequestInterface $req, ResponseInterface $res, array $args): ResponseInterface
    {
        $shareService = new ShareService(new ShareRepository(), new FileRepository(), new TeamRepository());
        $share = $shareService->resolve((string)$args['token']);
        $file = $share && $share['file_id'] ? $this->filesRepo()->find((int)$share['file_id']) : null;
        $user = $req->getAttribute('user');
        if ($share && $share['scope'] === 'private' && !$user) {
            return View::render($res, 'share_login', ['csrf_token' => Csrf::issue(null)['token'], 'token' => $args['token']]);
        }
        $data = [
            'share' => $share,
            'file' => $file,
            'token' => $args['token'],
            'csrf_token' => Csrf::issue(is_array($user) ? (int)$user['user_id'] : null)['token'],
        ];
        return View::render($res, 'share', $data);
    }

    public function download(ServerRequestInterface $req, ResponseInterface $res, array $args): ResponseInterface
    {
        $fileId = (int)$args['id'];
        $user = $req->getAttribute('user');
        $file = $this->filesRepo()->find($fileId);
        if (!$file) {
            throw new HttpException(404, 'file_not_found');
        }
        $teams = $this->teamsRepo()->listForUser(is_array($user) ? (int)$user['user_id'] : 0);
        $teamIds = array_map(static fn($t) => (int)$t['id'], $teams);
        if (!$this->canAccessFile($user, $file, $teamIds)) {
            throw new HttpException(403, 'file_forbidden');
        }
        $contents = Storage::read($file['storage_name']);
        $res->getBody()->write($contents);
        return $res->withHeader('Content-Type', $file['mime_type'] ?: 'application/octet-stream')
            ->withHeader('Content-Disposition', 'attachment; filename="' . rawurlencode($file['original_name']) . '"');
    }

    public function preview(ServerRequestInterface $req, ResponseInterface $res, array $args): ResponseInterface
    {
        $fileId = (int)$args['id'];
        $user = $req->getAttribute('user');
        $file = $this->filesRepo()->find($fileId);
        if (!$file) {
            throw new HttpException(404, 'file_not_found');
        }
        $teams = $this->teamsRepo()->listForUser(is_array($user) ? (int)$user['user_id'] : 0);
        $teamIds = array_map(static fn($t) => (int)$t['id'], $teams);
        if (!$this->canAccessFile($user, $file, $teamIds)) {
            throw new HttpException(403, 'file_forbidden');
        }
        $contents = Storage::read($file['storage_name']);
        $res->getBody()->write($contents);
        return $res->withHeader('Content-Type', $file['mime_type'] ?: 'text/plain');
    }

    public function downloadExport(ServerRequestInterface $req, ResponseInterface $res, array $args): ResponseInterface
    {
        $user = $this->requireUser($req, $res);
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $fileId = (int)$args['id'];
        $file = $this->filesRepo()->find($fileId);
        if (!$file || $file['purpose'] !== 'audit_export') {
            throw new HttpException(404, 'export_not_found');
        }
        if ((int)$file['owner_id'] !== (int)$user['user_id'] && $user['role'] !== 'admin') {
            throw new HttpException(403, 'export_forbidden');
        }
        $contents = Storage::read($file['storage_name']);
        $res->getBody()->write($contents);
        return $res->withHeader('Content-Type', $file['mime_type'] ?: 'application/octet-stream')
            ->withHeader('Content-Disposition', 'attachment; filename="' . rawurlencode($file['original_name']) . '"');
    }

    public function shareDownload(ServerRequestInterface $req, ResponseInterface $res, array $args): ResponseInterface
    {
        $service = new \App\Service\DownloadService(new FileRepository(), new ShareRepository(), new TeamRepository());
        return $service->streamForToken((string)$args['token'], 'download');
    }

    public function sharePreview(ServerRequestInterface $req, ResponseInterface $res, array $args): ResponseInterface
    {
        $service = new \App\Service\DownloadService(new FileRepository(), new ShareRepository(), new TeamRepository());
        return $service->streamForToken((string)$args['token'], 'preview');
    }

    public function error(ServerRequestInterface $req, ResponseInterface $res, array $args): ResponseInterface
    {
        return View::render($res, 'error', [
            'csrf_token' => Csrf::issue(null)['token'],
            'status' => (int)($args['status'] ?? 500),
            'message' => (string)($args['message'] ?? 'error'),
        ], (int)($args['status'] ?? 500));
    }

    private function baseContext(?array $user, ServerRequestInterface $req): array
    {
        $userId = is_array($user) ? (int)$user['user_id'] : null;
        $issued = Csrf::issue($userId);
        $sessionLifetime = Config::int('SESSION_LIFETIME', 7200);
        return [
            'csrf' => $issued['token'],
            'user' => $user,
            'is_admin' => is_array($user) && $user['role'] === 'admin',
            'app_name' => Config::get('APP_NAME') ?: 'Cloud File Sharing',
            'session_lifetime' => $sessionLifetime,
            'path' => $req->getUri()->getPath(),
        ];
    }

    private function requireUser(ServerRequestInterface $req, ResponseInterface $res): array|ResponseInterface
    {
        $user = $req->getAttribute('user');
        if (!is_array($user)) {
            $params = $req->getQueryParams();
            $redirect = $params['redirect'] ?? '/login';
            return $res->withHeader('Location', $redirect)->withStatus(302);
        }
        return $user;
    }

    private function usersRepo(): UserRepository
    {
        return new UserRepository();
    }

    private function quotasRepo(): QuotaRepository
    {
        return new QuotaRepository();
    }

    private function teamsRepo(): TeamRepository
    {
        return new TeamRepository();
    }

    private function filesRepo(): FileRepository
    {
        return new FileRepository();
    }

    private function canAccessFile(?array $user, array $file, array $teamIds): bool
    {
        if (is_array($user)) {
            if ((int)$file['owner_id'] === (int)$user['user_id']) {
                return true;
            }
            if ($user['role'] === 'admin') {
                return true;
            }
            if ($file['team_id'] && in_array((int)$file['team_id'], $teamIds, true)) {
                return true;
            }
        }
        return false;
    }
}