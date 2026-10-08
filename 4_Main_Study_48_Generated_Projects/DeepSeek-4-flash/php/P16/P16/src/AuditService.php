<?php

declare(strict_types=1);

namespace App;

final class AuditService
{
    public const ACTIONS = [
        'login', 'logout', 'register', 'config_change', 'config_approve', 'service_action',
        'job_create', 'job_run', 'job_pause', 'job_resume', 'job_delete', 'backup_create',
        'backup_upload', 'backup_restore', 'backup_delete', 'alert_action', 'health_check',
        'token_create', 'token_revoke', 'operator_create', 'operator_toggle', 'operator_role',
        'access_record', 'admin_operation',
    ];

    public function __construct(private readonly Database $db)
    {
    }

    public function write(
        array $user,
        string $action,
        string $module,
        string $detail,
        string $ip
    ): int {
        return $this->db->insert('audit_events', [
            'user_id' => (int) ($user['id'] ?? 0) ?: null,
            'username' => (string) ($user['username'] ?? ''),
            'action' => $action,
            'module' => mb_substr($module, 0, 64),
            'detail' => mb_substr($detail, 0, 2000),
            'ip_address' => mb_substr($ip, 0, 64),
            'created_at' => $this->db->now(),
        ]);
    }
}
