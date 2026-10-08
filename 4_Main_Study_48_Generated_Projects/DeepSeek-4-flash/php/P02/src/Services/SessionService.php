<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\SessionRepository;
use App\Repositories\UserRepository;

final class SessionService
{
    public function __construct(
        private readonly SessionRepository $sessions,
        private readonly UserRepository $users,
        private readonly int $lifetime
    ) {
    }

    public function start(array $user, string $userAgent = '', string $ip = ''): string
    {
        $token = bin2hex(random_bytes(32));
        $this->sessions->insert([
            'user_id' => (int) $user['id'],
            'token_hash' => hash('sha256', $token),
            'user_agent' => $userAgent,
            'ip_address' => $ip,
            'is_active' => 1,
            'expires_at' => date('Y-m-d H:i:s', time() + $this->lifetime),
        ]);
        return $token;
    }

    public function resolve(string $token): ?array
    {
        $session = $this->sessions->findActiveByTokenHash(hash('sha256', $token));
        if ($session === null) {
            return null;
        }
        return $this->users->getById((int) $session['user_id']);
    }

    public function destroy(string $token): bool
    {
        $session = $this->sessions->findByTokenHash(hash('sha256', $token));
        if ($session === null) {
            return false;
        }
        return $this->sessions->deactivate((int) $session['id']);
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
