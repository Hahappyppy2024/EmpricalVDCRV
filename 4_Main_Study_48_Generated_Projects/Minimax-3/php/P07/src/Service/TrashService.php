<?php
declare(strict_types=1);
namespace App\Service;

use App\Infrastructure\Audit;
use App\Infrastructure\HttpException;
use App\Infrastructure\Storage;
use App\Repository\FileRepository;
use App\Repository\FolderRepository;
use App\Repository\QuotaRepository;
use App\Repository\TeamRepository;
use App\Repository\TrashRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;

final class TrashService
{
    public function __construct(
        private TrashRepository $trash,
        private FileRepository $files,
        private FolderRepository $folders,
        private TeamRepository $teams,
        private QuotaRepository $quotas
    ) {
    }

    public function handle(ServerRequestInterface $req, Response $res, string $method, ?int $id): ResponseInterface
    {
        $user = $this->currentUser($req);
        if ($method === 'GET') {
            $items = $this->trash->listActive();
            $filtered = array_values(array_filter($items, function ($item) use ($user) {
                if ($user['role'] === 'admin') {
                    return true;
                }
                if ($item['owner_id'] !== null && (int)$item['owner_id'] === (int)$user['user_id']) {
                    return true;
                }
                if ($item['team_id']) {
                    $membership = $this->teams->membership((int)$item['team_id'], (int)$user['user_id']);
                    return $membership !== null;
                }
                return false;
            }));
            return $this->json($res, ['items' => $filtered]);
        }
        $params = (array)$req->getParsedBody();
        if ($method === 'POST') {
            $trashId = $this->sendToTrash($user, $params);
            return $this->json($res, ['status' => 'item_trashed', 'trash_id' => $trashId], 201);
        }
        if ($method === 'PATCH') {
            if (!$id) {
                throw new HttpException(400, 'missing_trash_id');
            }
            $this->perform($user, $id, $params);
            return $this->json($res, ['status' => 'trash_action_complete', 'trash_id' => $id]);
        }
        throw new HttpException(405, 'method_not_allowed');
    }

    private function sendToTrash(array $user, array $params): int
    {
        $type = (string)($params['target_type'] ?? '');
        $targetId = (int)($params['target_id'] ?? 0);
        if (!in_array($type, ['file', 'folder'], true) || $targetId <= 0) {
            throw new HttpException(422, 'invalid_target');
        }
        if ($type === 'file') {
            $file = $this->files->find($targetId);
            if (!$file) {
                throw new HttpException(404, 'file_not_found');
            }
            $this->ensureFileAccess($user, $file, true);
            $this->files->setStatus($targetId, 'trashed');
            $trashId = $this->trash->create([
                'item_type' => 'file',
                'item_id' => $targetId,
                'owner_id' => $file['owner_id'] !== null ? (int)$file['owner_id'] : null,
                'team_id' => $file['team_id'] !== null ? (int)$file['team_id'] : null,
                'original_name' => $file['original_name'],
                'original_parent_id' => $file['folder_id'] !== null ? (int)$file['folder_id'] : null,
                'deleted_by' => (int)$user['user_id'],
                'reason' => 'user_deleted',
            ]);
        } else {
            $folder = $this->folders->find($targetId);
            if (!$folder) {
                throw new HttpException(404, 'folder_not_found');
            }
            $this->ensureFolderAccess($user, $folder, true);
            $this->folders->setStatus($targetId, 'trashed');
            $trashId = $this->trash->create([
                'item_type' => 'folder',
                'item_id' => $targetId,
                'owner_id' => $folder['owner_id'] !== null ? (int)$folder['owner_id'] : null,
                'team_id' => $folder['team_id'] !== null ? (int)$folder['team_id'] : null,
                'original_name' => $folder['name'],
                'original_parent_id' => $folder['parent_id'] !== null ? (int)$folder['parent_id'] : null,
                'deleted_by' => (int)$user['user_id'],
                'reason' => 'user_deleted',
            ]);
        }
        Audit::log((int)$user['user_id'], 'trash_send', $type, $targetId, []);
        return $trashId;
    }

    private function perform(array $user, int $trashId, array $params): void
    {
        $item = $this->trash->findById($trashId);
        if (!$item || $item['purged_at']) {
            throw new HttpException(404, 'trash_not_found');
        }
        $this->ensureTrashAccess($user, $item, true);
        $action = (string)($params['action'] ?? '');
        match ($action) {
            'restore' => $this->restore($user, $item, $params),
            'purge' => $this->purge($user, $item),
            default => throw new HttpException(400, 'unknown_action'),
        };
    }

    private function restore(array $user, array $item, array $params): void
    {
        $parentId = isset($params['parent_id']) ? (int)$params['parent_id'] : (int)($item['original_parent_id'] ?? 0);
        if ($item['item_type'] === 'file') {
            $fields = ['folder_id' => $parentId > 0 ? $parentId : null];
            $this->files->updateMetadata((int)$item['item_id'], $fields);
            $this->files->setStatus((int)$item['item_id'], 'active');
        } else {
            $this->folders->move((int)$item['item_id'], $parentId > 0 ? $parentId : null, $item['owner_id'] !== null ? (int)$item['owner_id'] : null, $item['team_id'] !== null ? (int)$item['team_id'] : null);
            $this->folders->setStatus((int)$item['item_id'], 'active');
        }
        $this->trash->delete((int)$item['id']);
        $this->trash->log((int)$item['id'], (int)$user['user_id'], 'restore', true);
        Audit::log((int)$user['user_id'], 'trash_restore', $item['item_type'], (int)$item['item_id'], []);
    }

    private function purge(array $user, array $item): void
    {
        if ($item['item_type'] === 'file') {
            $file = $this->files->find((int)$item['item_id']);
            if ($file) {
                Storage::delete($file['storage_name']);
                if ($file['team_id']) {
                    $this->teams->adjustUsed((int)$file['team_id'], -((int)$file['size']));
                } elseif ($file['owner_id']) {
                    $this->quotas->adjustUsed((int)$file['owner_id'], -((int)$file['size']));
                }
                $this->files->delete((int)$file['id']);
            }
        } else {
            $folder = $this->folders->find((int)$item['item_id']);
            if ($folder) {
                foreach ($this->folders->descendantFolders((int)$folder['id']) as $desc) {
                    $this->folders->delete((int)$desc['id']);
                }
                $this->folders->delete((int)$folder['id']);
            }
        }
        $this->trash->markPurged((int)$item['id']);
        $this->trash->log((int)$item['id'], (int)$user['user_id'], 'purge', true);
        Audit::log((int)$user['user_id'], 'trash_purge', $item['item_type'], (int)$item['item_id'], []);
    }

    private function ensureFileAccess(array $user, array $file, bool $write = false): void
    {
        if ((int)$file['owner_id'] === (int)$user['user_id']) {
            return;
        }
        if ($file['team_id']) {
            $membership = $this->teams->membership((int)$file['team_id'], (int)$user['user_id']);
            if ($membership) {
                return;
            }
        }
        if ($user['role'] === 'admin') {
            return;
        }
        throw new HttpException(403, 'file_forbidden');
    }

    private function ensureFolderAccess(array $user, array $folder, bool $write = false): void
    {
        if ((int)$folder['owner_id'] === (int)$user['user_id']) {
            return;
        }
        if ($folder['team_id']) {
            $membership = $this->teams->membership((int)$folder['team_id'], (int)$user['user_id']);
            if ($membership) {
                return;
            }
        }
        if ($user['role'] === 'admin') {
            return;
        }
        throw new HttpException(403, 'folder_forbidden');
    }

    private function ensureTrashAccess(array $user, array $item, bool $write = false): void
    {
        if ($user['role'] === 'admin') {
            return;
        }
        if ($item['owner_id'] !== null && (int)$item['owner_id'] === (int)$user['user_id']) {
            return;
        }
        if ($item['team_id']) {
            $membership = $this->teams->membership((int)$item['team_id'], (int)$user['user_id']);
            if ($membership) {
                return;
            }
        }
        throw new HttpException(403, 'trash_forbidden');
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