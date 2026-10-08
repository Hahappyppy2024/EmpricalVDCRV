<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\AuditRepository;
use App\Repository\SessionRepository;
use App\Repository\UserRepository;

final class AccountAccessService
{
    private UserRepository $users;

    private SessionRepository $sessions;

    private AuditRepository $audit;

    public function __construct()
    {
        $this->users = new UserRepository();
        $this->sessions = new SessionRepository();
        $this->audit = new AuditRepository();
    }

    public function profile(int $userId): ?array
    {
        return $this->users->findById($userId);
    }

    public function accessLog(int $userId): array
    {
        return $this->sessions->recentAccess(50, $userId);
    }

    /**
     * @return array{0: ?string, 1: ?array} [error, updatedUser]
     */
    public function updateProfile(int $userId, string $fullName, string $email): array
    {
        $user = $this->users->findById($userId);
        if ($user === null) {
            return ['Unknown account.', null];
        }

        $existing = $this->users->findByUsernameOrEmail($email);
        if ($existing !== null && (int) $existing['id'] !== $userId) {
            return ['The email address is already registered.', null];
        }

        $this->users->update($userId, ['full_name' => $fullName, 'email' => $email]);
        $this->audit->record($userId, $user['username'], 'update', 'account_access', 'user', (string) $userId, 'Profile updated');

        return [null, $this->users->findById($userId)];
    }
}
