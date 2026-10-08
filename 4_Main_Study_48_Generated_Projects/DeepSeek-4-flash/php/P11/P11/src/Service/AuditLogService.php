<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\AuditRepository;

final class AuditLogService
{
    private AuditRepository $audit;

    public function __construct()
    {
        $this->audit = new AuditRepository();
    }

    /**
     * @return array{0: ?string, 1: array} [error, events]
     */
    public function search(array $user, ?string $module, ?string $action, ?string $username, ?int $limit): array
    {
        if ($user['role'] === 'customer') {
            $events = $this->audit->search($module, $action, $user['username'], 100);
            foreach ($events as $event) {
                if ($event['username'] !== $user['username']) {
                    // filtered out by the search above; nothing extra needed
                }
            }

            return [null, array_values(array_filter($events, fn ($e) => $e['username'] === $user['username']))];
        }

        $events = $this->audit->search($module, $action, $username, $limit ?? 200);

        return [null, $events];
    }

    public function modules(): array
    {
        return array_map(fn ($row) => $row['module'], $this->audit->search(null, null, null, 1));
    }

    public function allActions(): array
    {
        return ['create', 'update', 'delete', 'login', 'login_failed', 'register', 'logout', 'request', 'renew', 'upload', 'restore', 'deploy', 'toggle', 'run', 'reply', 'status', 'configure', 'reset'];
    }
}
