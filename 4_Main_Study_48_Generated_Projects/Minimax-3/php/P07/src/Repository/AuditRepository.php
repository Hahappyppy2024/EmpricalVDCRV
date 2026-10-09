<?php
declare(strict_types=1);
namespace App\Repository;

use App\Infrastructure\Database;

final class AuditRepository
{
    public function listEvents(int $limit = 200): array
    {
        $stmt = Database::pdo()->prepare('SELECT a.*, u.email AS user_email FROM audit_events a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.created_at DESC LIMIT ?');
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function listForUser(int $userId, int $limit = 200): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM audit_events WHERE user_id = ? ORDER BY created_at DESC LIMIT ?');
        $stmt->bindValue(1, $userId, \PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function createExport(int $userId, string $format, array $filters): int
    {
        $stmt = Database::pdo()->prepare('INSERT INTO audit_exports (user_id, format, status, filters) VALUES (?, ?, ?, ?)');
        $stmt->execute([$userId, $format, 'ready', json_encode($filters, JSON_UNESCAPED_UNICODE)]);
        return (int)Database::pdo()->lastInsertId();
    }

    public function attachFile(int $exportId, int $fileId): void
    {
        Database::pdo()->prepare('UPDATE audit_exports SET file_id = ? WHERE id = ?')->execute([$fileId, $exportId]);
    }

    public function findExport(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM audit_exports WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function listExportsForUser(int $userId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM audit_exports WHERE user_id = ? ORDER BY created_at DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function listExportsAll(): array
    {
        return Database::pdo()->query('SELECT * FROM audit_exports ORDER BY created_at DESC')->fetchAll();
    }

    public function log(int $exportId, ?int $userId, string $action, bool $success, array $details = []): void
    {
        Database::pdo()->prepare('INSERT INTO audit_log_exports (export_id, user_id, action, success, details) VALUES (?, ?, ?, ?, ?)')
            ->execute([$exportId, $userId, $action, $success ? 1 : 0, json_encode($details, JSON_UNESCAPED_UNICODE)]);
    }
}