<?php
declare(strict_types=1);
namespace App\Service;

use App\Infrastructure\Audit;
use App\Infrastructure\Config;
use App\Infrastructure\HttpException;
use App\Repository\AdminRepository;
use App\Repository\FolderRepository;
use App\Repository\QuotaRepository;
use App\Repository\TeamRepository;
use App\Repository\UserRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;

final class AdminService
{
    public function __construct(
        private AdminRepository $admin,
        private UserRepository $users,
        private QuotaRepository $quotas,
        private TeamRepository $teams,
        private FolderRepository $folders
    ) {
    }

    public function handle(ServerRequestInterface $req, Response $res, string $method, ?int $id): ResponseInterface
    {
        $user = $this->currentUser($req, true);
        if ($method === 'GET') {
            return $this->info($res);
        }
        $params = (array)$req->getParsedBody();
        if ($method === 'POST') {
            $action = (string)($params['action'] ?? '');
            return match ($action) {
                'create_user' => $this->createUser($user, $params, $res),
                'create_team' => $this->createTeam($user, $params, $res),
                'update_settings' => $this->updateSettings($user, $params, $res),
                default => throw new HttpException(400, 'unknown_action'),
            };
        }
        if ($method === 'PATCH') {
            if (!$id) {
                throw new HttpException(400, 'missing_target_id');
            }
            return $this->patchTarget($user, $id, $params, $res);
        }
        throw new HttpException(405, 'method_not_allowed');
    }

    private function info(Response $res): ResponseInterface
    {
        $users = $this->users->listAll();
        $settings = $this->admin->settings();
        $teams = $this->teams->listAll();
        return $this->json($res, ['users' => $users, 'settings' => $settings, 'teams' => $teams]);
    }

    private function createUser(array $user, array $params, Response $res): ResponseInterface
    {
        $name = trim((string)($params['name'] ?? ''));
        $email = trim((string)($params['email'] ?? ''));
        $password = (string)($params['password'] ?? '');
        $role = (string)($params['role'] ?? 'user');
        if ($name === '' || $email === '' || strlen($password) < 8 || !in_array($role, ['admin', 'user', 'recipient'], true)) {
            throw new HttpException(422, 'invalid_user_data');
        }
        if ($this->users->findByEmail($email)) {
            throw new HttpException(409, 'email_taken');
        }
        $userId = $this->users->create([
            'name' => $name,
            'email' => $email,
            'password_hash' => $this->hashPassword($password),
            'role' => $role,
        ]);
        $this->folders->create(['name' => $name . "'s home", 'owner_id' => $userId, 'parent_id' => null]);
        $default = (int)($this->admin->settings()['default_user_quota'] ?? 104857600);
        $this->quotas->upsert($userId, $default, 0);
        $this->admin->log(null, (int)$user['user_id'], 'create_user', true, ['email' => $email, 'role' => $role]);
        Audit::log((int)$user['user_id'], 'admin_create_user', 'user', $userId, ['role' => $role]);
        return $this->json($res, ['status' => 'user_created', 'user_id' => $userId], 201);
    }

    private function createTeam(array $user, array $params, Response $res): ResponseInterface
    {
        $name = trim((string)($params['name'] ?? ''));
        if ($name === '') {
            throw new HttpException(422, 'name_required');
        }
        $settings = $this->admin->settings();
        $teamId = $this->teams->create([
            'name' => $name,
            'description' => (string)($params['description'] ?? ''),
            'created_by' => (int)$user['user_id'],
            'quota_limit' => (int)($params['quota_limit'] ?? $settings['default_team_quota'] ?? 1073741824),
        ]);
        $folderId = $this->folders->create(['name' => $name . ' shared', 'team_id' => $teamId, 'owner_id' => null]);
        $this->teams->setRootFolder($teamId, $folderId);
        $this->teams->addMember($teamId, (int)$user['user_id'], 'owner');
        $this->admin->log(null, (int)$user['user_id'], 'create_team', true, ['team_id' => $teamId]);
        Audit::log((int)$user['user_id'], 'admin_create_team', 'team', $teamId, ['name' => $name]);
        return $this->json($res, ['status' => 'team_created', 'team_id' => $teamId], 201);
    }

    private function updateSettings(array $user, array $params, Response $res): ResponseInterface
    {
        $fields = [];
        if (isset($params['default_user_quota'])) {
            $fields['default_user_quota'] = max(0, (int)$params['default_user_quota']);
        }
        if (isset($params['default_team_quota'])) {
            $fields['default_team_quota'] = max(0, (int)$params['default_team_quota']);
        }
        if (isset($params['retention_days'])) {
            $fields['retention_days'] = max(1, (int)$params['retention_days']);
        }
        if (isset($params['blocked_file_types'])) {
            $fields['blocked_file_types'] = trim((string)$params['blocked_file_types']);
        }
        if ($fields === []) {
            throw new HttpException(400, 'nothing_to_update');
        }
        $this->admin->update($fields, (int)$user['user_id']);
        $this->admin->log(null, (int)$user['user_id'], 'update_settings', true, $fields);
        Audit::log((int)$user['user_id'], 'admin_update_settings', 'admin_settings', 1, $fields);
        return $this->json($res, ['status' => 'settings_updated', 'fields' => $fields]);
    }

    private function patchTarget(array $user, int $id, array $params, Response $res): ResponseInterface
    {
        $action = (string)($params['action'] ?? '');
        if ($action === 'update_user') {
            $target = $this->users->find($id);
            if (!$target) {
                throw new HttpException(404, 'user_not_found');
            }
            $fields = [];
            if (isset($params['status']) && in_array($params['status'], ['active', 'suspended'], true)) {
                $fields['status'] = (string)$params['status'];
            }
            if (isset($params['role']) && in_array($params['role'], ['admin', 'user', 'recipient'], true)) {
                $fields['role'] = (string)$params['role'];
            }
            if ($fields === []) {
                throw new HttpException(400, 'nothing_to_update');
            }
            $this->users->update($id, $fields);
            $this->admin->log(null, (int)$user['user_id'], 'update_user', true, ['user_id' => $id, 'fields' => $fields]);
            Audit::log((int)$user['user_id'], 'admin_update_user', 'user', $id, $fields);
            return $this->json($res, ['status' => 'user_updated', 'user_id' => $id]);
        }
        if ($action === 'update_team') {
            $team = $this->teams->find($id);
            if (!$team) {
                throw new HttpException(404, 'team_not_found');
            }
            $fields = [];
            if (isset($params['quota_limit'])) {
                $fields['quota_limit'] = max(0, (int)$params['quota_limit']);
            }
            if (isset($params['name'])) {
                $fields['name'] = trim((string)$params['name']);
            }
            if (isset($params['description'])) {
                $fields['description'] = (string)$params['description'];
            }
            if ($fields === []) {
                throw new HttpException(400, 'nothing_to_update');
            }
            $this->teams->update($id, $fields);
            $this->admin->log(null, (int)$user['user_id'], 'update_team', true, ['team_id' => $id, 'fields' => $fields]);
            Audit::log((int)$user['user_id'], 'admin_update_team', 'team', $id, $fields);
            return $this->json($res, ['status' => 'team_updated', 'team_id' => $id]);
        }
        if ($action === 'update_settings') {
            return $this->updateSettings($user, $params, $res);
        }
        throw new HttpException(400, 'unknown_action');
    }

    private function currentUser(ServerRequestInterface $req, bool $adminOnly = false): array
    {
        $user = $req->getAttribute('user');
        if (!is_array($user)) {
            throw new HttpException(401, 'authentication_required');
        }
        if ($adminOnly && $user['role'] !== 'admin') {
            throw new HttpException(403, 'admin_required');
        }
        return $user;
    }

    private function json(Response $res, array $payload, int $status = 200): Response
    {
        $res->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));
        return $res->withStatus($status)->withHeader('Content-Type', 'application/json');
    }

    private function hashPassword(string $password): string
    {
        $salt = (string)(\App\Infrastructure\Config::get('APP_SALT') ?: 'deterministic-salt');
        return hash('sha256', $password . $salt);
    }
}