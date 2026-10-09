<?php
declare(strict_types=1);
namespace App\Service;

use App\Infrastructure\Auth;
use App\Infrastructure\Config;
use App\Infrastructure\Csrf;
use App\Infrastructure\HttpException;
use App\Infrastructure\Mail;
use App\Repository\AccountRepository;
use App\Repository\FolderRepository;
use App\Repository\QuotaRepository;
use App\Repository\UserRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;

final class AccountService
{
    public function __construct(
        private UserRepository $users,
        private FolderRepository $folders,
        private QuotaRepository $quotas,
        private AccountRepository $log
    ) {
    }

    public function handle(ServerRequestInterface $req, Response $res, string $method, ?int $id): ResponseInterface
    {
        if ($method === 'GET') {
            return $this->info($req, $res);
        }
        $params = (array)$req->getParsedBody();
        $action = (string)($params['action'] ?? '');
        $ip = $req->getServerParams()['REMOTE_ADDR'] ?? null;
        return match ($action) {
            'register' => $this->register($res, $params, $ip),
            'login' => $this->login($res, $params, $ip),
            'recover' => $this->recover($res, $params, $ip),
            'logout' => $this->logout($req, $res, $ip),
            default => $this->patch($req, $res, $id, $params, $ip),
        };
    }

    private function patch(ServerRequestInterface $req, Response $res, ?int $id, array $params, ?string $ip): ResponseInterface
    {
        $action = (string)($params['action'] ?? '');
        return match ($action) {
            'update_profile' => $this->updateProfile($req, $res, $id, $params, $ip),
            'reset_password' => $this->resetPassword($res, $id, $params, $ip),
            'change_password' => $this->changePassword($req, $res, $id, $params, $ip),
            default => throw new HttpException(400, 'unknown_action'),
        };
    }

    private function info(ServerRequestInterface $req, Response $res): ResponseInterface
    {
        $user = $req->getAttribute('user');
        $userId = is_array($user) ? (int)$user['user_id'] : null;
        $issued = Csrf::issue($userId);
        $payload = [
            'auth' => $user ? [
                'id' => (int)$user['user_id'],
                'email' => $user['email'],
                'name' => $user['name'],
                'role' => $user['role'],
                'status' => $user['status'],
            ] : null,
            'csrf_token' => $issued['token'],
        ];
        return $this->json($res, $payload);
    }

    private function register(Response $res, array $params, ?string $ip): ResponseInterface
    {
        $name = trim((string)($params['name'] ?? ''));
        $email = trim((string)($params['email'] ?? ''));
        $password = (string)($params['password'] ?? '');
        if ($name === '' || $email === '' || strlen($password) < 8) {
            $this->log->record(null, 'register', 'invalid', ['email' => $email], $ip);
            throw new HttpException(422, 'invalid_registration');
        }
        if ($this->users->findByEmail($email)) {
            $this->log->record(null, 'register', 'duplicate', ['email' => $email], $ip);
            throw new HttpException(409, 'email_taken');
        }
        $userId = $this->users->create([
            'name' => $name,
            'email' => $email,
            'password_hash' => $this->hashPassword($password),
            'role' => 'user',
        ]);
        $this->folders->create(['name' => $name . "'s home", 'owner_id' => $userId, 'parent_id' => null]);
        $defaultQuota = Config::int('DEFAULT_USER_QUOTA', 104857600);
        $this->quotas->upsert($userId, $defaultQuota, 0);
        $this->log->record($userId, 'register', 'success', ['email' => $email], $ip);
        $session = Auth::start($userId, $ip, '');
        return $this->json($res, [
            'status' => 'registered',
            'user' => ['id' => $userId, 'email' => $email, 'name' => $name, 'role' => 'user'],
            'session_token' => $session['token'],
            'csrf_token' => $session['csrf'],
        ], 201)->withHeader('Set-Cookie', $this->cookieString($session['token']));
    }

    private function login(Response $res, array $params, ?string $ip): ResponseInterface
    {
        $email = trim((string)($params['email'] ?? ''));
        $password = (string)($params['password'] ?? '');
        $user = $this->users->findByEmail($email);
        if (!$user || !hash_equals($user['password_hash'], $this->hashPassword($password)) || $user['status'] !== 'active') {
            $this->log->record($user['id'] ?? null, 'login', 'failure', ['email' => $email], $ip);
            throw new HttpException(401, 'invalid_credentials');
        }
        $this->log->record((int)$user['id'], 'login', 'success', ['email' => $email], $ip);
        $session = Auth::start((int)$user['id'], $ip, '');
        return $this->json($res, [
            'status' => 'authenticated',
            'user' => ['id' => (int)$user['id'], 'email' => $user['email'], 'name' => $user['name'], 'role' => $user['role']],
            'session_token' => $session['token'],
            'csrf_token' => $session['csrf'],
        ])->withHeader('Set-Cookie', $this->cookieString($session['token']));
    }

    private function logout(ServerRequestInterface $req, Response $res, ?string $ip): ResponseInterface
    {
        $user = $req->getAttribute('user');
        if (is_array($user)) {
            Auth::destroy((string)$user['sid']);
            $this->log->record((int)$user['user_id'], 'logout', 'success', [], $ip);
        }
        [$value, $opts] = Auth::clearCookie();
        return $res->withHeader('Set-Cookie', $value . $this->formatCookieOpts($opts));
    }

    private function recover(Response $res, array $params, ?string $ip): ResponseInterface
    {
        $email = trim((string)($params['email'] ?? ''));
        $user = $this->users->findByEmail($email);
        if (!$user) {
            $this->log->record(null, 'recover', 'unknown_email', ['email' => $email], $ip);
            throw new HttpException(404, 'email_not_found');
        }
        $recovery = $this->log->createRecovery((int)$user['id']);
        Mail::send($email, 'Account recovery code', 'Use this code within one hour to reset your password: ' . $recovery['token']);
        $this->log->record((int)$user['id'], 'recover', 'success', ['email' => $email], $ip);
        return $this->json($res, [
            'status' => 'recovery_issued',
            'recovery_id' => $recovery['id'],
            'recovery_token' => $recovery['token'],
            'email' => $email,
        ]);
    }

    private function resetPassword(Response $res, ?int $id, array $params, ?string $ip): ResponseInterface
    {
        if (!$id) {
            throw new HttpException(400, 'missing_recovery_id');
        }
        $recovery = $this->log->findRecovery($id);
        if (!$recovery) {
            $this->log->record(null, 'reset_password', 'not_found', ['recovery_id' => $id], $ip);
            throw new HttpException(404, 'recovery_not_found');
        }
        if ($recovery['used_at']) {
            throw new HttpException(409, 'recovery_already_used');
        }
        if (strtotime($recovery['expires_at']) < time()) {
            throw new HttpException(410, 'recovery_expired');
        }
        $provided = (string)($params['token'] ?? '');
        if (!str_contains($provided, '.')) {
            throw new HttpException(400, 'invalid_token');
        }
        [$selector, $secret] = explode('.', $provided, 2);
        if ($selector !== $recovery['selector']) {
            throw new HttpException(400, 'selector_mismatch');
        }
        if (!hash_equals($recovery['token_hash'], hash('sha256', $secret))) {
            throw new HttpException(400, 'invalid_token');
        }
        $password = (string)($params['password'] ?? '');
        if (strlen($password) < 8) {
            throw new HttpException(422, 'weak_password');
        }
        $this->users->updatePassword((int)$recovery['user_id'], $this->hashPassword($password));
        $this->log->markRecoveryUsed($id);
        $this->log->record((int)$recovery['user_id'], 'reset_password', 'success', ['recovery_id' => $id], $ip);
        return $this->json($res, ['status' => 'password_reset']);
    }

    private function updateProfile(ServerRequestInterface $req, Response $res, ?int $id, array $params, ?string $ip): ResponseInterface
    {
        $user = $this->requireUser($req);
        if ($id !== (int)$user['user_id'] && $user['role'] !== 'admin') {
            throw new HttpException(403, 'forbidden');
        }
        $fields = [];
        if (isset($params['name'])) {
            $fields['name'] = trim((string)$params['name']);
        }
        if (isset($params['email'])) {
            $fields['email'] = trim((string)$params['email']);
        }
        if ($fields === []) {
            throw new HttpException(400, 'nothing_to_update');
        }
        $this->users->update($id, $fields);
        $this->log->record($id, 'update_profile', 'success', $fields, $ip);
        return $this->json($res, ['status' => 'profile_updated', 'fields' => $fields]);
    }

    private function changePassword(ServerRequestInterface $req, Response $res, ?int $id, array $params, ?string $ip): ResponseInterface
    {
        $user = $this->requireUser($req);
        if ($id !== (int)$user['user_id']) {
            throw new HttpException(403, 'forbidden');
        }
        $current = (string)($params['current_password'] ?? '');
        $new = (string)($params['password'] ?? '');
        $record = $this->users->find($id);
        if (!$record || !hash_equals($record['password_hash'], $this->hashPassword($current))) {
            $this->log->record($id, 'change_password', 'invalid_current', [], $ip);
            throw new HttpException(401, 'invalid_current_password');
        }
        if (strlen($new) < 8) {
            throw new HttpException(422, 'weak_password');
        }
        $this->users->updatePassword($id, $this->hashPassword($new));
        $this->log->record($id, 'change_password', 'success', [], $ip);
        return $this->json($res, ['status' => 'password_changed']);
    }

    private function requireUser(ServerRequestInterface $req): array
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

    private function cookieString(string $value): string
    {
        [$cookieValue, $opts] = Auth::cookieValue($value, Config::int('SESSION_LIFETIME', 7200));
        return $cookieValue . $this->formatCookieOpts($opts);
    }

    private function formatCookieOpts(array $opts): string
    {
        $parts = [];
        foreach ($opts as $k => $v) {
            if ($v === true) {
                $parts[] = ucfirst($k);
            } elseif ($v === false || $v === null) {
                continue;
            } else {
                $parts[] = ucfirst($k) . '=' . $v;
            }
        }
        return $parts === [] ? '' : '; ' . implode('; ', $parts);
    }

    private function hashPassword(string $password): string
    {
        $salt = (string)(Config::get('APP_SALT') ?: 'deterministic-salt');
        return hash('sha256', $password . $salt);
    }
}