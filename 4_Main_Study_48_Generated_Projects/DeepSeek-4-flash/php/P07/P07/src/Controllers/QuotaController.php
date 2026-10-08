<?php

declare(strict_types=1);

namespace CloudFS\Controllers;

use CloudFS\Database\Database;
use CloudFS\Repositories\UserRepository;
use CloudFS\Services\AuditService;
use CloudFS\Services\QuotaService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

final class QuotaController
{
    use JsonResponder;

    public function __construct(
        private Database $db,
        private UserRepository $users,
        private QuotaService $quota,
        private PhpRenderer $view,
        private AuditService $audit
    ) {
    }

    public function page(Request $request, Response $response): Response
    {
        return $this->view->render($response, 'quota.php', ['current_user' => $request->getAttribute('current_user')]);
    }

    public function index(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('current_user');
        $summary = $this->quota->summary((int) $user['id']);
        if (($user['role'] ?? 'user') === 'admin') {
            $summary['all_users'] = $this->quota->listAll();
        }
        return $this->json($response, ['ok' => true, 'quota' => $summary]);
    }

    public function create(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('current_user');
        $data = $this->parseBody($request);
        $this->quota->recompute((int) $user['id']);
        $this->audit->log((int) $user['id'], 'quota.recalculated', 'quota', (string) $user['id'], []);
        return $this->json($response, ['ok' => true, 'quota' => $this->quota->summary((int) $user['id']), 'message' => 'Quota recalculated.']);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('current_user');
        $data = $this->parseBody($request);
        $targetId = (int) $args['id'];
        if (($user['role'] ?? 'user') !== 'admin') {
            if ($targetId !== (int) $user['id']) {
                return $this->json($response, ['ok' => false, 'errors' => ['You can only adjust your own quota.']], 403);
            }
        }
        $target = $this->users->findById($targetId);
        if (!$target) {
            return $this->json($response, ['ok' => false, 'errors' => ['User not found.']], 404);
        }
        $quotaBytes = (int) ($data['quota_bytes'] ?? $target['quota_bytes']);
        $softLimit = (int) ($data['soft_limit'] ?? $quotaBytes);
        $hardLimit = (int) ($data['hard_limit'] ?? $quotaBytes);
        if ($quotaBytes <= 0 || $hardLimit < $softLimit) {
            return $this->json($response, ['ok' => false, 'errors' => ['Quota values must be positive and the hard limit cannot be below the soft limit.']], 422);
        }
        $this->quota->updateQuota($targetId, $quotaBytes, $softLimit, $hardLimit);
        $this->audit->log((int) $user['id'], 'quota.updated', 'user', (string) $targetId, ['quota_bytes' => $quotaBytes]);
        return $this->json($response, ['ok' => true, 'message' => 'Storage quota updated.']);
    }
}
