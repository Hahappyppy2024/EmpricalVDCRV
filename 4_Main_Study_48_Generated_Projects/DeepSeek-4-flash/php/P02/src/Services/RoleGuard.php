<?php

declare(strict_types=1);

namespace App\Services;

final class RoleGuard
{
    /**
     * @param array<string, mixed> $user
     * @param array<int, string> $roles
     */
    public static function require(array $user, array $roles): void
    {
        if (!in_array((string) $user['role'], $roles, true)) {
            throw new WorkflowException(
                'permission_error',
                403,
                ['role' => $user['role'] ?? null],
                'You are not allowed to perform this action.'
            );
        }
    }

    public static function isAdmin(array $user): bool
    {
        return $user['role'] === 'admin';
    }

    public static function isChair(array $user): bool
    {
        return in_array($user['role'], ['chair', 'admin'], true);
    }
}
