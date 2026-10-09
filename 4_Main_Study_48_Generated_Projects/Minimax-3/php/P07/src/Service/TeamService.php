<?php
declare(strict_types=1);
namespace App\Service;

use App\Infrastructure\HttpException;
use App\Repository\FileRepository;
use App\Repository\FolderRepository;
use App\Repository\QuotaRepository;
use App\Repository\TeamRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;

final class TeamService
{
    public function __construct(
        private TeamRepository $teams,
        private FolderRepository $folders,
        private FileRepository $files,
        private QuotaRepository $quotas
    ) {
    }

    public function handle(ServerRequestInterface $req, Response $res, string $method, ?int $id): ResponseInterface
    {
        $user = $this->currentUser($req);
        if ($method === 'GET') {
            if ($user['role'] === 'admin') {
                $teams = $this->teams->listAll();
            } else {
                $teams = $this->teams->listForUser((int)$user['user_id']);
            }
            $withMembers = array_map(function ($team) {
                $team['members'] = $this->teams->members((int)$team['id']);
                return $team;
            }, $teams);
            return $this->json($res, ['teams' => $withMembers]);
        }
        $params = (array)$req->getParsedBody();
        if ($method === 'POST') {
            $teamId = $this->create($user, $params);
            return $this->json($res, ['status' => 'team_created', 'team_id' => $teamId], 201);
        }
        if ($method === 'PATCH') {
            if (!$id) {
                throw new HttpException(400, 'missing_team_id');
            }
            $this->modify($user, $id, $params);
            return $this->json($res, ['status' => 'team_updated', 'team_id' => $id]);
        }
        throw new HttpException(405, 'method_not_allowed');
    }

    private function create(array $user, array $params): int
    {
        if ($user['role'] !== 'admin') {
            throw new HttpException(403, 'admin_required');
        }
        $name = trim((string)($params['name'] ?? ''));
        if ($name === '') {
            throw new HttpException(422, 'name_required');
        }
        $description = (string)($params['description'] ?? '');
        $quota = (int)($params['quota_limit'] ?? 1073741824);
        $teamId = $this->teams->create([
            'name' => $name,
            'description' => $description,
            'created_by' => (int)$user['user_id'],
            'quota_limit' => $quota,
        ]);
        $folderId = $this->folders->create([
            'name' => $name . ' shared',
            'team_id' => $teamId,
            'owner_id' => null,
        ]);
        $this->teams->setRootFolder($teamId, $folderId);
        $this->teams->addMember($teamId, (int)$user['user_id'], 'owner');
        $ownerId = isset($params['owner_id']) ? (int)$params['owner_id'] : null;
        if ($ownerId) {
            $this->teams->addMember($teamId, $ownerId, 'editor');
        }
        $this->teams->log($teamId, (int)$user['user_id'], 'create', true, ['name' => $name]);
        return $teamId;
    }

    private function modify(array $user, int $id, array $params): void
    {
        $team = $this->teams->find($id);
        if (!$team) {
            throw new HttpException(404, 'team_not_found');
        }
        $membership = $this->teams->membership($id, (int)$user['user_id']);
        $isAdmin = $user['role'] === 'admin';
        $action = (string)($params['action'] ?? '');
        if (!in_array($action, ['update', 'add_member', 'remove_member', 'change_role', 'delete'], true)) {
            throw new HttpException(400, 'unknown_action');
        }
        if (!$isAdmin && (!$membership || !in_array($membership['role'], ['owner'], true))) {
            throw new HttpException(403, 'team_role_insufficient');
        }
        match ($action) {
            'update' => $this->updateTeam($user, $team, $params, $isAdmin),
            'add_member' => $this->addMember($user, $team, $params, $isAdmin),
            'remove_member' => $this->removeMember($user, $team, $params, $isAdmin),
            'change_role' => $this->changeRole($user, $team, $params, $isAdmin),
            'delete' => $this->deleteTeam($user, $team, $isAdmin),
            default => throw new HttpException(400, 'unknown_action'),
        };
    }

    private function updateTeam(array $user, array $team, array $params, bool $isAdmin): void
    {
        $fields = [];
        if (isset($params['name'])) {
            $fields['name'] = trim((string)$params['name']);
        }
        if (isset($params['description'])) {
            $fields['description'] = (string)$params['description'];
        }
        if (isset($params['quota_limit']) && $isAdmin) {
            $fields['quota_limit'] = max(0, (int)$params['quota_limit']);
        }
        if ($fields !== []) {
            $this->teams->update((int)$team['id'], $fields);
            $this->teams->log((int)$team['id'], (int)$user['user_id'], 'update', true, $fields);
        }
    }

    private function addMember(array $user, array $team, array $params, bool $isAdmin): void
    {
        $memberId = (int)($params['user_id'] ?? 0);
        $role = (string)($params['role'] ?? 'editor');
        if ($memberId <= 0 || !in_array($role, ['owner', 'editor', 'viewer'], true)) {
            throw new HttpException(422, 'invalid_member');
        }
        $this->teams->addMember((int)$team['id'], $memberId, $role);
        $this->teams->log((int)$team['id'], (int)$user['user_id'], 'add_member', true, ['user_id' => $memberId, 'role' => $role]);
    }

    private function removeMember(array $user, array $team, array $params, bool $isAdmin): void
    {
        $memberId = (int)($params['user_id'] ?? 0);
        if ($memberId <= 0) {
            throw new HttpException(422, 'invalid_member');
        }
        $this->teams->removeMember((int)$team['id'], $memberId);
        $this->teams->log((int)$team['id'], (int)$user['user_id'], 'remove_member', true, ['user_id' => $memberId]);
    }

    private function changeRole(array $user, array $team, array $params, bool $isAdmin): void
    {
        $memberId = (int)($params['user_id'] ?? 0);
        $role = (string)($params['role'] ?? '');
        if ($memberId <= 0 || !in_array($role, ['owner', 'editor', 'viewer'], true)) {
            throw new HttpException(422, 'invalid_member');
        }
        $this->teams->updateMemberRole((int)$team['id'], $memberId, $role);
        $this->teams->log((int)$team['id'], (int)$user['user_id'], 'change_role', true, ['user_id' => $memberId, 'role' => $role]);
    }

    private function deleteTeam(array $user, array $team, bool $isAdmin): void
    {
        if (!$isAdmin) {
            throw new HttpException(403, 'admin_required');
        }
        $this->teams->delete((int)$team['id']);
        $this->teams->log((int)$team['id'], (int)$user['user_id'], 'delete', true);
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