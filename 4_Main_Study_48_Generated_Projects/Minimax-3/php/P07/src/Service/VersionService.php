<?php
declare(strict_types=1);
namespace App\Service;

use App\Infrastructure\Audit;
use App\Infrastructure\HttpException;
use App\Infrastructure\Storage;
use App\Repository\FileRepository;
use App\Repository\QuotaRepository;
use App\Repository\TeamRepository;
use App\Repository\VersionLogRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;

final class VersionService
{
    public function __construct(
        private FileRepository $files,
        private TeamRepository $teams,
        private QuotaRepository $quotas,
        private VersionLogRepository $log
    ) {
    }

    public function handle(ServerRequestInterface $req, Response $res, string $method, ?int $id): ResponseInterface
    {
        $user = $this->currentUser($req);
        if ($method === 'GET') {
            $query = $req->getQueryParams();
            $fileId = (int)($query['file_id'] ?? 0);
            if ($fileId <= 0) {
                throw new HttpException(400, 'missing_file_id');
            }
            $file = $this->files->find($fileId);
            if (!$file) {
                throw new HttpException(404, 'file_not_found');
            }
            $this->ensureFileAccess($user, $file);
            $versions = $this->files->versions($fileId);
            return $this->json($res, ['file' => $file, 'versions' => $versions]);
        }
        if ($method === 'POST') {
            return $this->createVersion($user, $req, $res);
        }
        if ($method === 'PATCH') {
            if (!$id) {
                throw new HttpException(400, 'missing_version_id');
            }
            $this->restoreVersion($user, $id, (array)$req->getParsedBody());
            return $this->json($res, ['status' => 'version_restored', 'version_id' => $id]);
        }
        throw new HttpException(405, 'method_not_allowed');
    }

    private function createVersion(array $user, ServerRequestInterface $req, Response $res): ResponseInterface
    {
        $uploaded = $req->getUploadedFiles();
        if (!isset($uploaded['file'])) {
            throw new HttpException(422, 'file_missing');
        }
        $params = (array)$req->getParsedBody();
        $fileId = (int)($params['file_id'] ?? 0);
        $summary = (string)($params['change_summary'] ?? '');
        $file = $this->files->find($fileId);
        if (!$file) {
            throw new HttpException(404, 'file_not_found');
        }
        $this->ensureFileAccess($user, $file, true);
        $upload = $uploaded['file'];
        $temp = $upload->getStream()->getMetadata('uri');
        $original = $upload->getClientFilename() ?: $file['original_name'];
        $size = (int)$upload->getSize();
        $stored = Storage::store($temp, $original);
        $newStoredId = $this->files->create([
            'folder_id' => $file['folder_id'],
            'owner_id' => $file['owner_id'],
            'team_id' => $file['team_id'],
            'original_name' => $original,
            'storage_name' => $stored['storage_name'],
            'description' => $file['description'],
            'mime_type' => $stored['mime_type'],
            'size' => $stored['size'],
            'checksum' => $stored['checksum'],
            'purpose' => 'user_file',
            'status' => 'active',
        ]);
        $next = $this->files->nextVersionNumber($fileId);
        $versionId = $this->files->createVersion($fileId, $next, $newStoredId, (int)$user['user_id'], $stored['size'], $summary);
        if ($file['team_id']) {
            $this->teams->adjustUsed((int)$file['team_id'], $stored['size'] - (int)$file['size']);
        } elseif ($file['owner_id']) {
            $this->quotas->adjustUsed((int)$file['owner_id'], $stored['size'] - (int)$file['size']);
        }
        $this->files->updateMetadata($fileId, ['original_name' => $original]);
        $this->files->setStatus($fileId, 'active');
        $this->log->log($versionId, (int)$user['user_id'], 'create', true, ['summary' => $summary]);
        Audit::log((int)$user['user_id'], 'version_create', 'stored_file', $fileId, ['version' => $next]);
        return $this->json($res, [
            'status' => 'version_uploaded',
            'version_id' => $versionId,
            'file_id' => $fileId,
            'version_number' => $next,
        ], 201);
    }

    private function restoreVersion(array $user, int $versionId, array $params): void
    {
        $stmt = \App\Infrastructure\Database::pdo()->prepare('SELECT v.*, sf.storage_name, sf.size AS version_size FROM versions v JOIN stored_files sf ON sf.id = v.stored_file_id WHERE v.id = ?');
        $stmt->execute([$versionId]);
        $version = $stmt->fetch();
        if (!$version) {
            throw new HttpException(404, 'version_not_found');
        }
        $file = $this->files->find((int)$version['file_id']);
        if (!$file) {
            throw new HttpException(404, 'file_not_found');
        }
        $this->ensureFileAccess($user, $file, true);
        $delta = (int)$version['version_size'] - (int)$file['size'];
        $this->files->updateMetadata((int)$file['id'], ['original_name' => $file['original_name']]);
        if ($file['team_id']) {
            $this->teams->adjustUsed((int)$file['team_id'], $delta);
        } elseif ($file['owner_id']) {
            $this->quotas->adjustUsed((int)$file['owner_id'], $delta);
        }
        $this->log->log($versionId, (int)$user['user_id'], 'restore', true, ['delta' => $delta]);
        Audit::log((int)$user['user_id'], 'version_restore', 'stored_file', (int)$file['id'], ['version' => (int)$version['version_number']]);
    }

    private function ensureFileAccess(array $user, array $file, bool $write = false): void
    {
        $teamIds = array_map(static fn($t) => (int)$t['id'], $this->teams->listForUser((int)$user['user_id']));
        if ((int)$file['owner_id'] === (int)$user['user_id']) {
            return;
        }
        if ($file['team_id'] && in_array((int)$file['team_id'], $teamIds, true)) {
            return;
        }
        if ($user['role'] === 'admin') {
            return;
        }
        throw new HttpException(403, 'file_forbidden');
    }

    private function currentUser(ServerRequestInterface $req): array
    {
        $user = $req->getAttribute('user');
        if (!is_array($user)) {
            throw new HttpException(401, 'authentication_required');
        }
        return $user;
    }

    private function json(Response $res, array $payload, int $status = 200): Response
    {
        $res->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));
        return $res->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}