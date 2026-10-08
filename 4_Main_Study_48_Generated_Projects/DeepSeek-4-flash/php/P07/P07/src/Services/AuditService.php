<?php

declare(strict_types=1);

namespace CloudFS\Services;

use CloudFS\Database\Database;

final class AuditService
{
    public function __construct(private Database $db)
    {
    }

    public function log(?int $userId, string $action, string $targetType, ?string $targetId, array $metadata = [], ?string $actorName = null): void
    {
        if ($actorName === null) {
            $actorName = $userId !== null
                ? (string) $this->db->value('SELECT username FROM users WHERE id = ?', [$userId])
                : 'anonymous';
        }
        $this->db->insert(
            'INSERT INTO audit_events (user_id, actor_name, action, target_type, target_id, ip_address, metadata, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, datetime(\'now\'))',
            [$userId, $actorName, $action, $targetType, $targetId, $_SERVER['REMOTE_ADDR'] ?? null, json_encode($metadata, JSON_UNESCAPED_UNICODE)]
        );
    }

    public function search(array $filters = []): array
    {
        $sql = 'SELECT * FROM audit_events WHERE 1=1';
        $params = [];
        if (!empty($filters['user_id'])) {
            $sql .= ' AND user_id = ?';
            $params[] = (int) $filters['user_id'];
        }
        if (!empty($filters['action'])) {
            $sql .= ' AND action LIKE ?';
            $params[] = '%' . $filters['action'] . '%';
        }
        if (!empty($filters['target_type'])) {
            $sql .= ' AND target_type = ?';
            $params[] = $filters['target_type'];
        }
        if (!empty($filters['from'])) {
            $sql .= ' AND created_at >= ?';
            $params[] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND created_at <= ?';
            $params[] = $filters['to'];
        }
        $limit = min((int) ($filters['limit'] ?? 200), 500);
        $sql .= ' ORDER BY created_at DESC, id DESC LIMIT ' . $limit;
        return $this->db->all($sql, $params);
    }

    public function export(array $filters, string $format): array
    {
        $rows = $this->search($filters);
        if ($format === 'json') {
            $payload = json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } else {
            $handle = fopen('php://temp', 'r+');
            fputcsv($handle, ['id', 'actor', 'action', 'target_type', 'target_id', 'created_at']);
            foreach ($rows as $row) {
                fputcsv($handle, [$row['id'], $row['actor_name'], $row['action'], $row['target_type'], $row['target_id'], $row['created_at']]);
            }
            rewind($handle);
            $payload = (string) stream_get_contents($handle);
            fclose($handle);
        }
        $filename = 'audit_export_' . gmdate('Ymd_His') . '.' . $format;
        return ['format' => $format, 'filename' => $filename, 'content' => $payload, 'rows' => count($rows)];
    }

    public function recordExport(int $userId, string $format, int $rowCount, string $filename): int
    {
        return $this->db->insert(
            'INSERT INTO audit_exports (user_id, format, row_count, filename, created_at) VALUES (?, ?, ?, ?, datetime(\'now\'))',
            [$userId, $format, $rowCount, $filename]
        );
    }

    public function listExports(int $userId): array
    {
        return $this->db->all(
            'SELECT * FROM audit_exports WHERE user_id = ? ORDER BY created_at DESC LIMIT 20',
            [$userId]
        );
    }
}
