<?php
declare(strict_types=1);
namespace App\Service;

use App\Infrastructure\HttpException;
use App\Infrastructure\Storage;
use App\Repository\FileRepository;
use App\Repository\ShareRepository;
use App\Repository\TeamRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;

final class DownloadService
{
    public function __construct(
        private FileRepository $files,
        private ShareRepository $shares,
        private TeamRepository $teams
    ) {
    }

    public function handle(ServerRequestInterface $req, Response $res, string $method, ?int $id): ResponseInterface
    {
        $user = $req->getAttribute('user');
        $params = $req->getQueryParams();
        $fileId = (int)($params['file_id'] ?? $id ?? 0);
        if ($fileId <= 0) {
            throw new HttpException(400, 'missing_file_id');
        }
        $file = $this->files->find($fileId);
        if (!$file || $file['status'] !== 'active' || $file['purpose'] !== 'user_file') {
            $this->files->recordDownload($fileId, is_array($user) ? (int)$user['user_id'] : null, 'unknown', $method === 'POST' ? 'download' : 'preview', false);
            throw new HttpException(404, 'file_not_found');
        }
        if (!$this->canAccess($user, $file, $params)) {
            $this->files->recordDownload($fileId, is_array($user) ? (int)$user['user_id'] : null, 'denied', $method === 'POST' ? 'download' : 'preview', false);
            throw new HttpException(403, 'file_forbidden');
        }
        if ($method === 'POST') {
            $this->files->recordDownload($fileId, is_array($user) ? (int)$user['user_id'] : null, 'owner', 'download', true);
            $res->getBody()->write(json_encode(['status' => 'download_recorded', 'file_id' => $fileId]));
            return $res->withStatus(200)->withHeader('Content-Type', 'application/json');
        }
        if ($method === 'GET') {
            $this->files->recordDownload($fileId, is_array($user) ? (int)$user['user_id'] : null, 'preview', 'preview', true);
            $contents = Storage::read($file['storage_name']);
            $res->getBody()->write($contents);
            return $res->withStatus(200)->withHeader('Content-Type', $file['mime_type'] ?: 'application/octet-stream');
        }
        if ($method === 'PATCH') {
            $this->files->updateMetadata($fileId, ['description' => (string)($req->getParsedBody()['description'] ?? $file['description'])]);
            return $res->withStatus(200)->withHeader('Content-Type', 'application/json');
        }
        throw new HttpException(405, 'method_not_allowed');
    }

    public function streamForToken(string $token, string $action): ResponseInterface
    {
        $share = $this->shares->findByPlainToken($token);
        if (!$share || $share['revoked_at'] || ($share['expires_at'] && strtotime($share['expires_at']) < time())) {
            throw new HttpException(404, 'share_not_found');
        }
        if ($share['scope'] === 'private' && !isset($_SESSION['user_id'])) {
            throw new HttpException(403, 'login_required');
        }
        $fileId = (int)$share['file_id'];
        $file = $this->files->find($fileId);
        if (!$file) {
            throw new HttpException(404, 'file_not_found');
        }
        if ($action === 'download' && $share['permission'] !== 'download') {
            throw new HttpException(403, 'download_not_allowed');
        }
        $this->files->recordDownload($fileId, null, 'share', $action, true);
        $contents = Storage::read($file['storage_name']);
        $res = new Response();
        $res->getBody()->write($contents);
        return $res->withStatus(200)->withHeader('Content-Type', $file['mime_type'] ?: 'application/octet-stream');
    }

    private function canAccess(?array $user, array $file, array $params): bool
    {
        if ($file['owner_id'] && is_array($user) && (int)$file['owner_id'] === (int)$user['user_id']) {
            return true;
        }
        if (is_array($user) && $user['role'] === 'admin') {
            return true;
        }
        if ($file['team_id'] && is_array($user)) {
            $membership = $this->teams->membership((int)$file['team_id'], (int)$user['user_id']);
            if ($membership) {
                return true;
            }
        }
        if (!empty($params['share_token'])) {
            $share = $this->shares->findByPlainToken((string)$params['share_token']);
            if ($share && (int)$share['file_id'] === (int)$file['id'] && !$share['revoked_at']) {
                return true;
            }
        }
        return false;
    }
}