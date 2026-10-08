<?php
declare(strict_types=1);

namespace App;

use PDO;
use Psr\Http\Message\ServerRequestInterface;

final class Auth
{
    public function __construct(private PDO $db, private string $cookieName, private int $ttl) {}

    public function current(ServerRequestInterface $request): ?array
    {
        $token = $request->getCookieParams()[$this->cookieName] ?? null;
        if (!is_string($token) || $token === '') return null;
        $stmt = $this->db->prepare("SELECT u.id,u.email,u.display_name,u.role,u.bio,u.timezone FROM sessions s JOIN users u ON u.id=s.user_id WHERE s.id=? AND s.expires_at > datetime('now')");
        $stmt->execute([hash('sha256', $token)]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function requireUser(ServerRequestInterface $request, array $roles = []): array
    {
        $user = $this->current($request);
        if (!$user) throw new ApiException(401, 'authentication_required', 'Sign in is required.');
        if ($roles && !in_array($user['role'], $roles, true)) throw new ApiException(403, 'forbidden', 'Your account cannot perform this operation.');
        return $user;
    }

    public function createSession(int $userId): array
    {
        $plain = bin2hex(random_bytes(32));
        $expires = gmdate('Y-m-d H:i:s', time() + $this->ttl);
        $this->db->prepare('INSERT INTO sessions(id,user_id,expires_at) VALUES(?,?,?)')->execute([hash('sha256', $plain), $userId, $expires]);
        return [$plain, $expires];
    }

    public function destroy(ServerRequestInterface $request): void
    {
        $token = $request->getCookieParams()[$this->cookieName] ?? '';
        if (is_string($token) && $token !== '') $this->db->prepare('DELETE FROM sessions WHERE id=?')->execute([hash('sha256', $token)]);
    }

    public function cookie(string $value, int $maxAge): string
    {
        return sprintf('%s=%s; Path=/; Max-Age=%d; HttpOnly; SameSite=Lax', $this->cookieName, rawurlencode($value), $maxAge);
    }
}
