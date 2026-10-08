<?php
declare(strict_types=1);

namespace App;

use PDO;
use Psr\Http\Message\ServerRequestInterface as Request;

final class Auth
{
    public function __construct(private PDO $db, private string $cookieName, private int $ttl)
    {
    }

    public function create(int $userId): string
    {
        $token = bin2hex(random_bytes(24));
        $this->db->prepare("INSERT INTO sessions(id,user_id,expires_at) VALUES(?,?,datetime('now',?))")
            ->execute([hash('sha256', $token), $userId, '+' . $this->ttl . ' seconds']);
        return $token;
    }

    public function cookie(string $token, int $maxAge): string
    {
        return $this->cookieName . '=' . rawurlencode($token) . '; Path=/; HttpOnly; SameSite=Lax; Max-Age=' . $maxAge;
    }

    public function current(Request $request): ?array
    {
        $token = $request->getCookieParams()[$this->cookieName] ?? '';
        if ($token === '') {
            return null;
        }
        $statement = $this->db->prepare("SELECT u.id,u.email,u.name,u.role,u.status,u.quota_bytes FROM sessions s JOIN users u ON u.id=s.user_id WHERE s.id=? AND s.expires_at>datetime('now')");
        $statement->execute([hash('sha256', $token)]);
        $user = $statement->fetch();
        return $user ?: null;
    }

    public function requireUser(Request $request, array $roles = ['user','admin']): array
    {
        $user = $this->current($request);
        if (!$user) {
            throw new ApiException(401, 'authentication_required', 'Authentication is required.');
        }
        if ($user['status'] !== 'active') {
            throw new ApiException(403, 'account_disabled', 'This account is disabled.');
        }
        if (!in_array($user['role'], $roles, true)) {
            throw new ApiException(403, 'role_required', 'The current role cannot perform this operation.');
        }
        return $user;
    }

    public function destroy(Request $request): void
    {
        $token = $request->getCookieParams()[$this->cookieName] ?? '';
        if ($token !== '') {
            $this->db->prepare('DELETE FROM sessions WHERE id=?')->execute([hash('sha256', $token)]);
        }
    }
}
