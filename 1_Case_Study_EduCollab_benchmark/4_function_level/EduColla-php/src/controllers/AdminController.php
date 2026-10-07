<?php
require_once APP_BASE . '/src/repos/AuditLogRepo.php';

class AdminController {
    public function __construct(private PDO $pdo) {}

    // HTML: admin audit log view
    public function auditLog(): void {
        $user = current_user();
        if (!is_global_admin($user)) {
            http_response_code(403);
            echo "Forbidden";
            exit;
        }
        $repo = new AuditLogRepo($this->pdo);

        // Log the viewing action (non-critical). Critical events are intentionally not logged.
        $repo->add($user['user_id'] ?? null, 'audit_view', null, ['path' => $_SERVER['REQUEST_URI'] ?? '']);

        $logs = $repo->listRecent(200);
        render('admin/audit', [
            'title' => 'Audit Log',
            'logs'  => $logs,
        ]);
    }

    // API: list recent logs (admin only) - useful for testing/analysis
    public function apiListAudit(): void {
        $user = current_user();
        if (!is_global_admin($user)) {
            json_response(['error' => 'forbidden'], 403);
        }
        $repo = new AuditLogRepo($this->pdo);
        $logs = $repo->listRecent(200);
        json_response(['logs' => $logs], 200);
    }
}
