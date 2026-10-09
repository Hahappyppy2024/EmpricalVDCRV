<?php
declare(strict_types=1);
namespace App\Service;

use App\Infrastructure\HttpException;
use App\Repository\FileRepository;
use App\Repository\ShareRepository;
use App\Repository\TeamRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;

final class ShareService
{
    public function __construct(
        private ShareRepository $shares,
        private FileRepository $files,
        private TeamRepository $teams
    ) {
    }

    public function handle(ServerRequestInterface $req, Response $res, string $method, ?int $id): ResponseInterface
    {
        $user = $this->currentUser($req);
        if ($method === 'GET') {
            $shares = $this->shares->listForOwner((int)$user['user_id']);
            $incoming = $this->shares->listPrivateForUser((int)$user['user_id']);
            return $this->json($res, ['created' => $shares, 'incoming' => $incoming]);
        }
        if ($method === 'POST') {
            $params = (array)$req->getParsedBody();
            $share = $this->createShare($user, $params);
            return $this->json($res, $share, 201);
        }
        if ($method === 'PATCH') {
            if (!$id) {
                throw new HttpException(400, 'missing_share_id');
            }
            $this->update($user, $id, (array)$req->getParsedBody());
            return $this->json($res, ['status' => 'share_updated', 'share_id' => $id]);
        }
        throw new HttpException(405, 'method_not_allowed');
    }

    public function resolve(string $token): ?array
    {
        return $this->shares->findByPlainToken($token);
    }

    private function createShare(array $user, array $params): array
    {
        $type = (string)($params['target_type'] ?? '');
        $targetId = (int)($params['target_id'] ?? 0);
        $scope = (string)($params['scope'] ?? 'public');
        $permission = (string)($params['permission'] ?? 'preview');
        $expiresAt = $params['expires_at'] ?? null;
        $sharedWith = isset($params['shared_with_user_id']) ? (int)$params['shared_with_user_id'] : null;
        if (!in_array($type, ['file', 'folder'], true) || $targetId <= 0) {
            throw new HttpException(422, 'invalid_target');
        }
        if (!in_array($scope, ['public', 'private'], true)) {
            throw new HttpException(422, 'invalid_scope');
        }
        if (!in_array($permission, ['preview', 'download'], true)) {
            throw new HttpException(422, 'invalid_permission');
        }
        if ($scope === 'private' && !$sharedWith) {
            throw new HttpException(422, 'recipient_required');
        }
        if ($type === 'file') {
            $file = $this->files->find($targetId);
            if (!$file || $file['status'] !== 'active') {
                throw new HttpException(404, 'file_not_found');
            }
            if ((int)$file['owner_id'] !== (int)$user['user_id'] && $user['role'] !== 'admin') {
                throw new HttpException(403, 'file_forbidden');
            }
            $data = ['file_id' => $targetId, 'folder_id' => null];
        } else {
            $folder = $this->folders()->find($targetId);
            if (!$folder) {
                throw new HttpException(404, 'folder_not_found');
            }
            if ((int)$folder['owner_id'] !== (int)$user['user_id'] && $user['role'] !== 'admin') {
                throw new HttpException(403, 'folder_forbidden');
            }
            $data = ['file_id' => null, 'folder_id' => $targetId];
        }
        $plain = bin2hex(random_bytes(16));
        $data['created_by'] = (int)$user['user_id'];
        $data['scope'] = $scope;
        $data['permission'] = $permission;
        $data['expires_at'] = $expiresAt;
        $data['shared_with_user_id'] = $sharedWith;
        $id = $this->shares->create($data, $plain);
        $this->shares->log($id, (int)$user['user_id'], 'create', true);
        return [
            'status' => 'share_created',
            'share_id' => $id,
            'token' => $plain,
            'url' => '/s/' . $plain,
            'scope' => $scope,
            'permission' => $permission,
            'expires_at' => $expiresAt,
        ];
    }

    private function update(array $user, int $id, array $params): void
    {
        $share = $this->shares->find($id);
        if (!$share) {
            throw new HttpException(404, 'share_not_found');
        }
        if ((int)$share['created_by'] !== (int)$user['user_id'] && $user['role'] !== 'admin') {
            throw new HttpException(403, 'share_forbidden');
        }
        $fields = [];
        if (isset($params['expires_at'])) {
            $fields['expires_at'] = (string)$params['expires_at'];
        }
        if (isset($params['permission']) && in_array($params['permission'], ['preview', 'download'], true)) {
            $fields['permission'] = (string)$params['permission'];
        }
        if (isset($params['scope']) && in_array($params['scope'], ['public', 'private'], true)) {
            $fields['scope'] = (string)$params['scope'];
        }
        if (isset($params['revoke'])) {
            $fields['revoked_at'] = date('Y-m-d H:i:s');
        }
        if ($fields === []) {
            throw new HttpException(400, 'nothing_to_update');
        }
        $this->shares->update($id, $fields);
        $this->shares->log($id, (int)$user['user_id'], 'update', true, $fields);
    }

    private function currentUser(ServerRequestInterface $req): array
    {
        $user = $req->getAttribute('user');
        if (!is_array($user)) {
            throw new HttpException(401, 'authentication_required');
        }
        return $user;
    }

    private function folders(): \App\Repository\FolderRepository
    {
        return new \App\Repository\FolderRepository();
    }

    private function json(Response $res, array $payload, int $status = 200): Response
    {
        $res->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));
        return $res->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}