<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Server-side session service. Sessions are persisted in SQLite and the
 * browser receives an HTTP-only cookie holding an unguessable token. The
 * token is stored hashed in the database.
 */
final class AuthService
{
    public const COOKIE = 'lms_session';

    public static function tokenFromRequest(Request $request): ?string
    {
        if (isset($_COOKIE[self::COOKIE])) {
            return (string) $_COOKIE[self::COOKIE];
        }
        $params = $request->getCookieParams();
        if (isset($params[self::COOKIE])) {
            return (string) $params[self::COOKIE];
        }
        $header = $request->getHeaderLine('Cookie');
        if ($header !== '') {
            foreach (explode(';', $header) as $pair) {
                [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
                if (trim($key) === self::COOKIE) {
                    return trim($value);
                }
            }
        }
        return null;
    }

    public function __construct(private Database $db, private \App\Config $config)
    {
    }

    /**
     * Create a persisted session and return the raw cookie token. Callers
     * attach the token to the response via cookieHeader().
     */
    public function createSession(int $userId, string $ip): string
    {
        $token = bin2hex(random_bytes(32));
        $lifetime = $this->config->sessionLifetimeMinutes();
        $this->db->insert('sessions', [
            'user_id' => $userId,
            'token_hash' => hash('sha256', $token),
            'expires_at' => date('Y-m-d H:i:s', time() + $lifetime * 60),
            'ip_address' => $ip,
        ]);
        return $token;
    }

    public static function cookieHeader(string $token, int $lifetimeMinutes = 480): string
    {
        return AuthService::COOKIE . '=' . $token . '; path=/; HttpOnly; SameSite=Lax; Max-Age=' . ($lifetimeMinutes * 60);
    }

    public static function clearCookieHeader(): string
    {
        return AuthService::COOKIE . '=; path=/; HttpOnly; SameSite=Lax; Max-Age=0';
    }

    /**
     * Resolve the signed-in user for the given cookie token, or null.
     *
     * @return array<string, mixed>|null
     */
    public function userFromToken(?string $token): ?array
    {
        if ($token === null || $token === '') {
            return null;
        }
        $hash = hash('sha256', $token);
        $session = $this->db->first(
            'SELECT s.*, u.*, r.name AS role_name
             FROM sessions s
             JOIN users u ON u.id = s.user_id
             JOIN roles r ON r.id = u.role_id
             WHERE s.token_hash = ?',
            [$hash]
        );
        if ($session === null) {
            return null;
        }
        if (strtotime($session['expires_at']) < time()) {
            $this->destroyToken($token);
            return null;
        }
        return $session;
    }

    public function destroyToken(string $token): void
    {
        $this->db->execute('DELETE FROM sessions WHERE token_hash = ?', [hash('sha256', $token)]);
        if (PHP_SAPI !== 'cli') {
            if (isset($_COOKIE[self::COOKIE])) {
                unset($_COOKIE[self::COOKIE]);
            }
            @setcookie(self::COOKIE, '', [
                'expires' => time() - 3600,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }

    public function attemptLogin(string $usernameOrEmail, string $password, string $ip): array
    {
        $user = $this->db->first(
            'SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.username = ? OR u.email = ?',
            [$usernameOrEmail, $usernameOrEmail]
        );
        if ($user === null || $user['active'] != 1 || !password_verify($password, $user['password_hash'])) {
            return ['error' => 'Invalid username/email or password'];
        }
        $token = $this->createSession((int) $user['id'], $ip);
        $this->db->insert('account_access_log', ['user_id' => $user['id'], 'action' => 'login', 'detail_json' => json_encode(['ip' => $ip])]);
        return ['user' => $user, 'token' => $token];
    }

    public function register(string $username, string $email, string $password, string $displayName, string $ip): array
    {
        $exists = $this->db->first('SELECT id FROM users WHERE username = ? OR email = ?', [$username, $email]);
        if ($exists !== null) {
            return ['error' => 'A user with that username or email already exists'];
        }
        $roleId = (int) $this->db->first('SELECT id FROM roles WHERE name = ?', [$this->config->allowRegistration() ? 'student' : 'visitor'])['id'];
        $userId = $this->db->insert('users', [
            'username' => $username,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'display_name' => $displayName,
            'role_id' => $roleId,
            'active' => 1,
        ]);
        $token = $this->createSession($userId, $ip);
        $this->db->insert('account_access_log', ['user_id' => $userId, 'action' => 'register', 'detail_json' => json_encode(['ip' => $ip])]);
        $user = $this->db->first('SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?', [$userId]);
        return ['user' => $user, 'token' => $token];
    }

    /**
     * Create a password reset token and return the plain token (delivered via
     * the local mail adapter in a real deployment).
     */
    public function createPasswordReset(string $usernameOrEmail): array
    {
        $user = $this->db->first('SELECT * FROM users WHERE username = ? OR email = ?', [$usernameOrEmail, $usernameOrEmail]);
        if ($user === null) {
            return ['error' => 'No account matches that username/email'];
        }
        $token = bin2hex(random_bytes(24));
        $this->db->insert('password_resets', [
            'user_id' => $user['id'],
            'token_hash' => hash('sha256', $token),
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
        ]);
        $this->db->insert('account_access_log', ['user_id' => $user['id'], 'action' => 'forgot_password', 'detail_json' => json_encode([])]);
        return ['token' => $token, 'user_id' => (int) $user['id']];
    }

    public function resetPassword(string $token, string $newPassword): array
    {
        $row = $this->db->first('SELECT * FROM password_resets WHERE token_hash = ?', [hash('sha256', $token)]);
        if ($row === null || $row['used_at'] !== null || strtotime($row['expires_at']) < time()) {
            return ['error' => 'The reset token is invalid or has expired'];
        }
        $this->db->update('users', ['password_hash' => password_hash($newPassword, PASSWORD_DEFAULT)], 'id = :id', ['id' => (int) $row['user_id']]);
        $this->db->update('password_resets', ['used_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => (int) $row['id']]);
        $this->db->insert('account_access_log', ['user_id' => $row['user_id'], 'action' => 'password_reset', 'detail_json' => json_encode([])]);
        return ['ok' => true];
    }

    public function logAccess(int $userId, string $action, array $detail = []): void
    {
        $this->db->insert('account_access_log', ['user_id' => $userId, 'action' => $action, 'detail_json' => json_encode($detail)]);
    }
}
