<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\PlanRepository;
use App\Repository\SessionRepository;
use App\Repository\UserRepository;

final class AuthService
{
    private UserRepository $users;

    private SessionRepository $sessions;

    private PlanRepository $plans;

    public function __construct()
    {
        $this->users = new UserRepository();
        $this->sessions = new SessionRepository();
        $this->plans = new PlanRepository();
    }

    /**
     * @return array{0: ?string, 1: ?string} [error, sessionToken]
     */
    public function login(string $identifier, string $password): array
    {
        $user = $this->users->findByUsernameOrEmail($identifier);
        if ($user === null || !password_verify($password, $user['password_hash'])) {
            $this->sessions->logAccess(null, $identifier, 'login_failed', $this->ip());

            return ['Invalid username, email or password.', null];
        }

        $session = $this->sessions->create((int) $user['id']);
        $this->sessions->logAccess((int) $user['id'], $user['username'], 'login', $this->ip());

        return [null, $session['id']];
    }

    /**
     * @return array{0: ?string, 1: ?string} [error, sessionToken]
     */
    public function register(string $username, string $email, string $password, string $fullName, string $role = 'customer'): array
    {
        if ($this->users->existsUsername($username)) {
            return ['The username is already taken.', null];
        }
        if ($this->users->existsEmail($email)) {
            return ['The email address is already registered.', null];
        }

        $plan = $this->plans->findByName('Free');
        $userId = $this->users->create(
            $username,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            $fullName,
            $role,
            $plan !== null ? (int) $plan['id'] : null
        );
        $session = $this->sessions->create($userId);
        $this->sessions->logAccess($userId, $username, 'register', $this->ip());

        return [null, $session['id']];
    }

    public function logout(string $sessionId, int $userId, string $username): void
    {
        $this->sessions->delete($sessionId);
        $this->sessions->logAccess($userId, $username, 'logout', $this->ip());
    }

    private function ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
    }
}
