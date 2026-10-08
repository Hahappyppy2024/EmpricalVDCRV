<?php

declare(strict_types=1);

namespace CloudFS\Controllers;

use CloudFS\Database\Database;
use CloudFS\Repositories\FileRepository;
use CloudFS\Services\AuditService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

final class SharingLinksController
{
    use JsonResponder;

    public function __construct(
        private Database $db,
        private FileRepository $files,
        private PhpRenderer $view,
        private AuditService $audit
    ) {
    }

    public function page(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('current_user');
        return $this->view->render($response, 'sharing.php', ['current_user' => $user]);
    }

    public function index(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('current_user');
        return $this->json($response, ['ok' => true, 'shares' => $this->files->listSharesForOwner((int) $user['id'])]);
    }

    public function create(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('current_user');
        $data = $this->parseBody($request);
        $fileId = (int) ($data['file_id'] ?? 0);
        $scope = (string) ($data['scope'] ?? 'public');
        $permissions = (string) ($data['permissions'] ?? 'view');
        $expiresAt = isset($data['expires_at']) && $data['expires_at'] !== '' ? (string) $data['expires_at'] : null;
        $password = isset($data['password']) && $data['password'] !== '' ? (string) $data['password'] : null;

        if (!in_array($scope, ['public', 'private'], true)) {
            return $this->json($response, ['ok' => false, 'errors' => ['Scope must be public or private.']], 422);
        }
        if (!in_array($permissions, ['view', 'download', 'edit'], true)) {
            return $this->json($response, ['ok' => false, 'errors' => ['Permissions must be view, download or edit.']], 422);
        }
        if ($expiresAt !== null && !strtotime($expiresAt)) {
            return $this->json($response, ['ok' => false, 'errors' => ['Expiration date is not valid.']], 422);
        }
        $result = $this->files->createShare($fileId, (int) $user['id'], $scope, $permissions, $expiresAt, $password);
        if (!$result['ok']) {
            return $this->json($response, ['ok' => false, 'errors' => $result['errors']], 422);
        }
        $this->audit->log((int) $user['id'], 'share.created', 'share', (string) $fileId, ['scope' => $scope, 'permissions' => $permissions]);
        return $this->json($response, ['ok' => true, 'token' => $result['token'], 'url' => '/s/' . $result['token'], 'message' => 'Share link created.'], 201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('current_user');
        $data = $this->parseBody($request);
        $shareId = (int) $args['id'];

        if (!empty($data['action']) && $data['action'] === 'revoke') {
            $result = $this->files->revokeShare($shareId, (int) $user['id']);
            if (!$result['ok']) {
                return $this->json($response, ['ok' => false, 'errors' => $result['errors']], 404);
            }
            $this->audit->log((int) $user['id'], 'share.revoked', 'share', (string) $shareId, []);
            return $this->json($response, ['ok' => true, 'message' => 'Share link revoked.']);
        }
        $permissions = isset($data['permissions']) && in_array($data['permissions'], ['view', 'download', 'edit'], true) ? (string) $data['permissions'] : null;
        $scope = isset($data['scope']) && in_array($data['scope'], ['public', 'private'], true) ? (string) $data['scope'] : null;
        $expiresAt = array_key_exists('expires_at', $data) ? ($data['expires_at'] !== '' ? (string) $data['expires_at'] : null) : null;
        $result = $this->files->updateShare($shareId, (int) $user['id'], $permissions, $expiresAt, $scope);
        if (!$result['ok']) {
            return $this->json($response, ['ok' => false, 'errors' => $result['errors']], 404);
        }
        $this->audit->log((int) $user['id'], 'share.updated', 'share', (string) $shareId, []);
        return $this->json($response, ['ok' => true, 'message' => 'Share link updated.']);
    }
}
