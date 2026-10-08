<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;

/**
 * Exports, bulk reports, audit events and system settings.
 */
final class SystemRepository
{
    public function __construct(private Database $db)
    {
    }

    // ---- Settings ----

    /**
     * @return array<string, string>
     */
    public function allSettings(): array
    {
        $rows = $this->db->select('SELECT key, value FROM settings ORDER BY key');
        $out = [];
        foreach ($rows as $row) {
            $out[$row['key']] = $row['value'];
        }
        return $out;
    }

    public function setting(string $key, string $default = ''): string
    {
        $row = $this->db->first('SELECT value FROM settings WHERE key = ?', [$key]);
        return $row === null ? $default : (string) $row['value'];
    }

    public function setSetting(string $key, string $value): void
    {
        $existing = $this->db->first('SELECT key FROM settings WHERE key = ?', [$key]);
        if ($existing !== null) {
            $this->db->update('settings', ['value' => $value, 'updated_at' => date('Y-m-d H:i:s')], 'key = :key', ['key' => $key]);
            return;
        }
        $this->db->insert('settings', ['key' => $key, 'value' => $value]);
    }

    // ---- Exports ----

    /**
     * @return list<array<string, mixed>>
     */
    public function exportsForUser(int $userId, bool $all = false): array
    {
        $sql = 'SELECT x.*, u.display_name AS requester_name, c.title AS course_title
                FROM exports x JOIN users u ON u.id = x.requested_by LEFT JOIN courses c ON c.id = x.course_id WHERE 1 = 1';
        $params = [];
        if (!$all) {
            $sql .= ' AND x.requested_by = ?';
            $params[] = $userId;
        }
        $sql .= ' ORDER BY x.id DESC LIMIT 100';
        return $this->db->select($sql, $params);
    }

    public function export(int $id): ?array
    {
        return $this->db->first('SELECT x.*, c.title AS course_title FROM exports x LEFT JOIN courses c ON c.id = x.course_id WHERE x.id = ?', [$id]);
    }

    public function addExport(array $data): int
    {
        return $this->db->insert('exports', $data);
    }

    // ---- Reports ----

    /**
     * @return list<array<string, mixed>>
     */
    public function reports(int $userId = 0, bool $all = true): array
    {
        $sql = 'SELECT r.*, u.display_name AS requester_name FROM reports r JOIN users u ON u.id = r.requested_by WHERE 1 = 1';
        $params = [];
        if (!$all && $userId > 0) {
            $sql .= ' AND r.requested_by = ?';
            $params[] = $userId;
        }
        $sql .= ' ORDER BY r.id DESC LIMIT 100';
        return $this->db->select($sql, $params);
    }

    public function report(int $id): ?array
    {
        return $this->db->first('SELECT * FROM reports WHERE id = ?', [$id]);
    }

    public function addReport(array $data): int
    {
        return $this->db->insert('reports', $data);
    }

    /**
     * Aggregated counts used by the bulk course report.
     *
     * @return array<string, mixed>
     */
    public function bulkStats(array $filters = []): array
    {
        $where = '1 = 1';
        $params = [];
        if (($filters['semester'] ?? '') !== '' && $filters['semester'] !== 'all') {
            $where .= ' AND c.semester = ?';
            $params[] = $filters['semester'];
        }
        if (($filters['category'] ?? '') !== '' && $filters['category'] !== 'all') {
            $where .= ' AND c.category = ?';
            $params[] = $filters['category'];
        }
        if (($filters['instructor_id'] ?? '') !== '' && $filters['instructor_id'] !== 'all') {
            $where .= ' AND c.instructor_id = ?';
            $params[] = (int) $filters['instructor_id'];
        }
        if (($filters['status'] ?? '') !== '' && $filters['status'] !== 'all') {
            $where .= ' AND c.status = ?';
            $params[] = $filters['status'];
        }
        $stats = $this->db->first(
            'SELECT COUNT(DISTINCT c.id) AS total_courses,
                    COUNT(DISTINCT u.id) AS total_users,
                    COUNT(DISTINCT e.id) AS total_enrollments,
                    (SELECT COUNT(*) FROM materials m JOIN courses c2 ON c2.id = m.course_id WHERE ' . str_replace('c.', 'c2.', $where) . ') AS total_materials
             FROM courses c
             LEFT JOIN users u ON u.id = c.instructor_id
             LEFT JOIN enrollments e ON e.course_id = c.id AND e.status = "enrolled"
             WHERE ' . $where,
            $params
        );
        return [
            'total_courses' => (int) $stats['total_courses'],
            'total_users' => (int) $stats['total_users'],
            'total_enrollments' => (int) $stats['total_enrollments'],
            'total_materials' => (int) $stats['total_materials'],
        ];
    }

    // ---- Audit ----

    /**
     * @return list<array<string, mixed>>
     */
    public function auditEvents(int $limit = 200): array
    {
        return $this->db->select(
            'SELECT a.*, COALESCE(u.username, "system") AS username FROM audit_events a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.id DESC LIMIT ' . max(1, $limit)
        );
    }

    public function audit(array $data): void
    {
        $this->db->insert('audit_events', $data);
    }
}
