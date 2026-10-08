<?php

declare(strict_types=1);

namespace App;

abstract class BaseService implements ModuleService
{
    protected Database $db;
    protected AuditService $audit;
    protected Config $config;

    public function __construct(Database $db, AuditService $audit, Config $config)
    {
        $this->db = $db;
        $this->audit = $audit;
        $this->config = $config;
    }

    /**
     * @param array<string, mixed> $user
     *
     * @return array<string, mixed>
     */
    protected function requireRow(string $table, int $id, string $alias = '*'): array
    {
        $row = $this->db->fetchOne('SELECT ' . $alias . ' FROM ' . $table . ' WHERE id = ?', [$id]);
        if ($row === null) {
            throw new NotFoundException('Record not found.');
        }

        return $row;
    }

    protected function requireRole(array $user, array $allowed): void
    {
        if (!in_array((string) ($user['role'] ?? ''), $allowed, true)) {
            throw new ForbiddenException('You are not allowed to perform this operation.');
        }
    }

    /** @param array<string, mixed> $user */
    protected function ip(array $user): string
    {
        return (string) ($user['ip'] ?? '');
    }

    protected function log(array $user, string $action, string $module, string $detail): void
    {
        $this->audit->write($user, $action, $module, $detail, $this->ip($user));
    }
}
