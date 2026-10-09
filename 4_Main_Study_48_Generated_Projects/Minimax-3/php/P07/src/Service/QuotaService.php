<?php
declare(strict_types=1);
namespace App\Service;

use App\Infrastructure\HttpException;
use App\Repository\QuotaRepository;
use App\Repository\TeamRepository;
use App\Repository\UserRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;

final class QuotaService
{
    public function __construct(
        private QuotaRepository $quotas,
        private UserRepository $users,
        private TeamRepository $teams
    ) {
    }

    public function handle(ServerRequestInterface $req, Response $res, string $method, ?int $id): ResponseInterface
    {
        $user = $this->currentUser($req);
        if ($method === 'GET') {
            $params = $req->getQueryParams();
            if (isset($params['user_id']) && (int)$params['user_id'] !== (int)$user['user_id'] && $user['role'] !== 'admin') {
                throw new HttpException(403, 'quota_forbidden');
            }
            $userId = (int)($params['user_id'] ?? $user['user_id']);
            $quota = $this->quotas->getForUser($userId);
            $teamRows = [];
            foreach ($this->teams->listForUser($userId) as $team) {
                $teamRows[] = [
                    'team' => $team,
                    'usage' => ['used' => (int)$team['used_bytes'], 'limit' => (int)$team['quota_limit']],
                ];
            }
            return $this->json($res, ['quota' => $quota, 'teams' => $teamRows]);
        }
        $params = (array)$req->getParsedBody();
        if ($method === 'POST') {
            if ($user['role'] !== 'admin') {
                throw new HttpException(403, 'admin_required');
            }
            $userId = (int)($params['user_id'] ?? 0);
            $limit = (int)($params['limit_bytes'] ?? 0);
            if ($userId <= 0 || $limit < 0) {
                throw new HttpException(422, 'invalid_quota');
            }
            $this->quotas->upsert($userId, $limit, 0);
            $this->quotas->log(0, (int)$user['user_id'], 'create', true, ['user_id' => $userId, 'limit' => $limit]);
            return $this->json($res, ['status' => 'quota_created', 'user_id' => $userId]);
        }
        if ($method === 'PATCH') {
            if (!$id) {
                throw new HttpException(400, 'missing_quota_id');
            }
            if ($user['role'] !== 'admin') {
                throw new HttpException(403, 'admin_required');
            }
            $fields = [];
            if (isset($params['limit_bytes'])) {
                $fields['limit_bytes'] = max(0, (int)$params['limit_bytes']);
            }
            if (isset($params['recalculate_used'])) {
                $quota = $this->quotas->getForUser($id);
                if ($quota) {
                    $this->quotas->recalc($id, (int)$params['recalculate_used']);
                }
            }
            if ($fields !== []) {
                $existing = $this->quotas->getForUser($id);
                if (!$existing) {
                    throw new HttpException(404, 'quota_not_found');
                }
                $this->quotas->upsert($id, $fields['limit_bytes'], (int)$existing['used_bytes']);
                $this->quotas->log((int)$existing['id'], (int)$user['user_id'], 'update', true, $fields);
            }
            return $this->json($res, ['status' => 'quota_updated', 'user_id' => $id]);
        }
        throw new HttpException(405, 'method_not_allowed');
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