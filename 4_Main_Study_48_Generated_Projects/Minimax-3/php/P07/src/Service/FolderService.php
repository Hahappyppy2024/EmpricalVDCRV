<?php
declare(strict_types=1);
namespace App\Service;

use App\Infrastructure\HttpException;
use App\Repository\FileRepository;
use App\Repository\FolderLogRepository;
use App\Repository\FolderRepository;
use App\Repository\TeamRepository;
use App\Repository\TrashRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;

final class FolderService
{
    public function __construct(
        private FolderRepository $folders,
        private FileRepository $files,
        private TrashRepository $trash,
        private FolderLogRepository $log,
        private TeamRepository $teams
    ) {
    }

    public function handle(ServerRequestInterface $req, Response $res, string $method, ?int $id): ResponseInterface
    {
        $user = $this->currentUser($req);
        $params = (array)$req->getParsedBody();
        if ($method === 'GET') {
            $teamIds = $this->userTeamIds((int)$user['user_id']);
            $folders = $this->folders->listAccessibleForUser((int)$user['user_id'], $teamIds);
            return $this->json($res, ['folders' => $folders]);
        }
        if ($method === 'POST') {
            $folderId = $this->create($user, $params);
            return $this->json($res, ['status' => 'folder_created', 'folder_id' => $folderId], 201);
        }
        if ($method === 'PATCH') {
            if (!$id) {
                throw new HttpException(400, 'missing_folder_id');
            }
            $this->modify($user, $id, $params);
            return $this->json($res, ['status' => 'folder_updated', 'folder_id' => $id]);
        }
        throw new HttpException(405, 'method_not_allowed');
    }

    private function create(array $user, array $params): int
    {
        $name = trim((string)($params['name'] ?? ''));
        if ($name === '') {
            throw new HttpException(422, 'name_required');
        }
        $parentId = isset($params['parent_id']) ? (int)$params['parent_id'] : null;
        $teamId = isset($params['team_id']) ? (int)$params['team_id'] : null;
        $ownerId = $teamId ? null : (int)$user['user_id'];
        if ($teamId) {
            $this->requireTeamAccess($user, $teamId, ['owner', 'editor']);
        }
        if ($parentId) {
            $parent = $this->folders->find($parentId);
            if (!$parent || $parent['status'] !== 'active') {
                throw new HttpException(404, 'parent_not_found');
            }
            if ((int)$parent['owner_id'] !== (int)$user['user_id'] && $user['role'] !== 'admin' && !$teamId) {
                throw new HttpException(403, 'parent_forbidden');
            }
        }
        $id = $this->folders->create([
            'name' => $name,
            'owner_id' => $ownerId,
            'team_id' => $teamId,
            'parent_id' => $parentId,
            'status' => 'active',
        ]);
        $this->log->log($id, (int)$user['user_id'], 'create', true, ['name' => $name]);
        return $id;
    }

    private function modify(array $user, int $id, array $params): void
    {
        $folder = $this->folders->find($id);
        if (!$folder) {
            throw new HttpException(404, 'folder_not_found');
        }
        $action = (string)($params['action'] ?? '');
        if ($folder['team_id']) {
            $this->requireTeamAccess($user, (int)$folder['team_id'], ['owner', 'editor']);
        } elseif ((int)$folder['owner_id'] !== (int)$user['user_id'] && $user['role'] !== 'admin') {
            throw new HttpException(403, 'folder_forbidden');
        }
        match ($action) {
            'rename' => $this->renameFolder($user, $folder, $params),
            'move' => $this->moveFolder($user, $folder, $params),
            'delete' => $this->deleteFolder($user, $folder),
            default => throw new HttpException(400, 'unknown_action'),
        };
    }

    private function renameFolder(array $user, array $folder, array $params): void
    {
        $name = trim((string)($params['name'] ?? ''));
        if ($name === '') {
            throw new HttpException(422, 'name_required');
        }
        $this->folders->rename((int)$folder['id'], $name);
        $this->log->log((int)$folder['id'], (int)$user['user_id'], 'rename', true, ['name' => $name]);
    }

    private function moveFolder(array $user, array $folder, array $params): void
    {
        $parentId = isset($params['parent_id']) ? (int)$params['parent_id'] : null;
        $this->folders->move((int)$folder['id'], $parentId, (int)$folder['owner_id'], $folder['team_id'] !== null ? (int)$folder['team_id'] : null);
        $this->log->log((int)$folder['id'], (int)$user['user_id'], 'move', true, ['parent_id' => $parentId]);
    }

    private function deleteFolder(array $user, array $folder): void
    {
        $this->folders->setStatus((int)$folder['id'], 'trashed');
        $this->trash->create([
            'item_type' => 'folder',
            'item_id' => (int)$folder['id'],
            'owner_id' => $folder['owner_id'] !== null ? (int)$folder['owner_id'] : null,
            'team_id' => $folder['team_id'] !== null ? (int)$folder['team_id'] : null,
            'original_name' => $folder['name'],
            'original_parent_id' => $folder['parent_id'] !== null ? (int)$folder['parent_id'] : null,
            'deleted_by' => (int)$user['user_id'],
            'reason' => 'user_deleted',
        ]);
        foreach ($this->folders->descendantFolders((int)$folder['id']) as $desc) {
            $this->folders->setStatus((int)$desc['id'], 'trashed');
            $this->trash->create([
                'item_type' => 'folder',
                'item_id' => (int)$desc['id'],
                'owner_id' => $desc['owner_id'] !== null ? (int)$desc['owner_id'] : null,
                'team_id' => $desc['team_id'] !== null ? (int)$desc['team_id'] : null,
                'original_name' => $desc['name'],
                'original_parent_id' => $desc['parent_id'] !== null ? (int)$desc['parent_id'] : null,
                'deleted_by' => (int)$user['user_id'],
                'reason' => 'cascade',
            ]);
        }
        foreach ($this->files->listInFolder((int)$folder['id']) as $file) {
            $this->files->setStatus((int)$file['id'], 'trashed');
            $this->trash->create([
                'item_type' => 'file',
                'item_id' => (int)$file['id'],
                'owner_id' => $file['owner_id'] !== null ? (int)$file['owner_id'] : null,
                'team_id' => $file['team_id'] !== null ? (int)$file['team_id'] : null,
                'original_name' => $file['original_name'],
                'original_parent_id' => (int)$file['folder_id'],
                'deleted_by' => (int)$user['user_id'],
                'reason' => 'folder_deleted',
            ]);
        }
        $this->log->log((int)$folder['id'], (int)$user['user_id'], 'delete', true, []);
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