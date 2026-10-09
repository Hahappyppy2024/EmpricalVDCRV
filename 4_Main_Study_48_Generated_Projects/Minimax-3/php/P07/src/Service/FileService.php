<?php
declare(strict_types=1);
namespace App\Service;

use App\Infrastructure\Audit;
use App\Infrastructure\HttpException;
use App\Infrastructure\Storage;
use App\Repository\AdminRepository;
use App\Repository\FileRepository;
use App\Repository\FolderRepository;
use App\Repository\QuotaRepository;
use App\Repository\TeamRepository;
use App\Repository\TrashRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;

final class FileService
{
    public function __construct(
        private FileRepository $files,
        private FolderRepository $folders,
        private QuotaRepository $quotas,
        private TeamRepository $teams,
        private AdminRepository $admin,
        private TrashRepository $trash
    ) {
    }

    public function handle(ServerRequestInterface $req, Response $res, string $method, ?int $id): ResponseInterface
    {
        $user = $this->currentUser($req);
        if ($method === 'GET') {
            return $this->listFiles($user, $req, $res);
        }
        if ($method === 'POST') {
            return $this->upload($user, $req, $res);
        }
        if ($method === 'PATCH') {
            if (!$id) {
                throw new HttpException(400, 'missing_file_id');
            }
            $this->update($user, $id, (array)$req->getParsedBody());
            return $this->json($res, ['status' => 'file_updated', 'file_id' => $id]);
        }
        throw new HttpException(405, 'method_not_allowed');
    }

    private function listFiles(array $user, ServerRequestInterface $req, Response $res): ResponseInterface
    {
        $params = $req->getQueryParams();
        $folderId = isset($params['folder_id']) ? (int)$params['folder_id'] : null;
        $teamIds = $this->userTeamIds((int)$user['user_id']);
        if ($folderId) {
            $folder = $this->folders->find($folderId);
            if (!$folder) {
                throw new HttpException(404, 'folder_not_found');
            }
            $this->ensureFolderAccess($user, $folder, $teamIds);
            $files = $this->files->listInFolder($folderId);
            $children = $this->folders->listChildren($folderId);
            return $this->json($res, ['folder' => $folder, 'files' => $files, 'children' => $children]);
        }
        $files = $this->files->listSharedWith((int)$user['user_id'], $teamIds);
        return $this->json($res, ['files' => $files]);
    }

    private function upload(array $user, ServerRequestInterface $req, Response $res): ResponseInterface
    {
        $uploaded = $req->getUploadedFiles();
        if (!isset($uploaded['file']) || $uploaded['file']->getError() !== UPLOAD_ERR_OK) {
            throw new HttpException(422, 'file_missing');
        }
        $file = $uploaded['file'];
        $temp = $file->getStream()->getMetadata('uri');
        $original = $file->getClientFilename() ?? 'upload.bin';
        $size = (int)$file->getSize();
        if ($size <= 0) {
            throw new HttpException(422, 'empty_file');
        }
        $settings = $this->admin->settings() ?? [];
        $blocked = array_filter(array_map('trim', explode(',', (string)($settings['blocked_file_types'] ?? ''))));
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if ($blocked !== [] && in_array($ext, $blocked, true)) {
            throw new HttpException(415, 'file_type_blocked');
        }
        $params = (array)$req->getParsedBody();
        $folderId = isset($params['folder_id']) ? (int)$params['folder_id'] : null;
        $teamId = isset($params['team_id']) ? (int)$params['team_id'] : null;
        $description = (string)($params['description'] ?? '');
        $tags = array_values(array_filter(array_map('trim', explode(',', (string)($params['tags'] ?? '')))));
        $ownerId = $teamId ? null : (int)$user['user_id'];
        $teamIds = $this->userTeamIds((int)$user['user_id']);
        if ($folderId) {
            $folder = $this->folders->find($folderId);
            if (!$folder) {
                throw new HttpException(404, 'folder_not_found');
            }
            $this->ensureFolderAccess($user, $folder, $teamIds);
            $ownerId = $folder['owner_id'] !== null ? (int)$folder['owner_id'] : $ownerId;
            $teamId = $folder['team_id'] !== null ? (int)$folder['team_id'] : null;
        }
        if ($teamId) {
            $this->requireTeamAccess($user, $teamId, ['owner', 'editor']);
            $team = $this->teams->find($teamId);
            if (!$team) {
                throw new HttpException(404, 'team_not_found');
            }
            if ((int)$team['used_bytes'] + $size > (int)$team['quota_limit']) {
                throw new HttpException(413, 'team_quota_exceeded');
            }
        } else {
            $quota = $this->quotas->getForUser((int)$user['user_id']);
            if (!$quota) {
                $default = (int)($settings['default_user_quota'] ?? 104857600);
                $this->quotas->upsert((int)$user['user_id'], $default, 0);
                $quota = $this->quotas->getForUser((int)$user['user_id']);
            }
            if ((int)$quota['used_bytes'] + $size > (int)$quota['limit_bytes']) {
                throw new HttpException(413, 'user_quota_exceeded');
            }
        }
        $stored = Storage::store($temp, $original);
        $fileId = $this->files->create([
            'folder_id' => $folderId,
            'owner_id' => $ownerId,
            'team_id' => $teamId,
            'original_name' => $original,
            'storage_name' => $stored['storage_name'],
            'description' => $description,
            'mime_type' => $stored['mime_type'],
            'size' => $stored['size'],
            'checksum' => $stored['checksum'],
            'purpose' => 'user_file',
            'status' => 'active',
        ]);
        $this->files->setTags($fileId, $tags);
        $this->files->recordUpload($fileId, (int)$user['user_id'], $folderId, $original, $description, $tags, $stored['size']);
        $this->files->createVersion($fileId, 1, $fileId, (int)$user['user_id'], $stored['size'], 'initial upload');
        if ($teamId) {
            $this->teams->adjustUsed($teamId, $stored['size']);
        } else {
            $this->quotas->adjustUsed((int)$user['user_id'], $stored['size']);
        }
        Audit::log((int)$user['user_id'], 'file_upload', 'stored_file', $fileId, ['size' => $stored['size']]);
        return $this->json($res, [
            'status' => 'uploaded',
            'file' => [
                'id' => $fileId,
                'original_name' => $original,
                'size' => $stored['size'],
                'mime_type' => $stored['mime_type'],
                'tags' => $tags,
            ],
        ], 201);
    }

    private function update(array $user, int $id, array $params): void
    {
        $file = $this->files->find($id);
        if (!$file) {
            throw new HttpException(404, 'file_not_found');
        }
        $teamIds = $this->userTeamIds((int)$user['user_id']);
        $this->ensureFileAccess($user, $file, $teamIds, true);
        $fields = [];
        if (isset($params['description'])) {
            $fields['description'] = (string)$params['description'];
        }
        if (isset($params['original_name'])) {
            $fields['original_name'] = (string)$params['original_name'];
        }
        if (array_key_exists('folder_id', $params)) {
            $newFolder = (int)$params['folder_id'];
            if ($newFolder > 0) {
                $folder = $this->folders->find($newFolder);
                if (!$folder) {
                    throw new HttpException(404, 'folder_not_found');
                }
                $this->ensureFolderAccess($user, $folder, $teamIds);
                $fields['folder_id'] = $newFolder;
            } else {
                $fields['folder_id'] = null;
            }
        }
        if ($fields !== []) {
            $this->files->updateMetadata($id, $fields);
        }
        if (isset($params['tags'])) {
            $tags = array_values(array_filter(array_map('trim', explode(',', (string)$params['tags']))));
            $this->files->setTags($id, $tags);
        }
        Audit::log((int)$user['user_id'], 'file_update', 'stored_file', $id, $fields);
    }

    private function ensureFolderAccess(array $user, array $folder, array $teamIds): void
    {
        if ((int)$folder['owner_id'] === (int)$user['user_id']) {
            return;
        }
        if ($folder['team_id'] && in_array((int)$folder['team_id'], $teamIds, true)) {
            return;
        }
        if ($user['role'] === 'admin') {
            return;
        }
        throw new HttpException(403, 'folder_forbidden');
    }

    private function ensureFileAccess(array $user, array $file, array $teamIds, bool $write = false): void
    {
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

    private function userTeamIds(int $userId): array
    {
        $teams = $this->teams->listForUser($userId);
        return array_map(static fn($t) => (int)$t['id'], $teams);
    }

    private function requireTeamAccess(array $user, int $teamId, array $roles): void
    {
        $membership = $this->teams->membership($teamId, (int)$user['user_id']);
        if (!$membership || $membership['status'] !== 'active') {
            throw new HttpException(403, 'team_forbidden');
        }
        if (!in_array($membership['role'], $roles, true) && $user['role'] !== 'admin') {
            throw new HttpException(403, 'team_role_insufficient');
        }
    }

    private function json(Response $res, array $payload, int $status = 200): Response
    {
        $res->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));
        return $res->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}