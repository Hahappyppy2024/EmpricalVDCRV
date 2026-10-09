<?php
declare(strict_types=1);
namespace App\Repository;

use App\Infrastructure\Database;

final class TrashRepository
{
    public function create(array $data): int
    {
        $stmt = Database::pdo()->prepare('INSERT OR REPLACE INTO trash_items (item_type, item_id, owner_id, team_id, original_name, original_parent_id, deleted_by, reason, purged_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $data['item_type'],
            $data['item_id'],
            $data['owner_id'] ?? null,
            $data['team_id'] ?? null,
            $data['original_name'],
            $data['original_parent_id'] ?? null,
            $data['deleted_by'],
            $data['reason'] ?? null,
            $data['purged_at'] ?? null,
        ]);
        return (int)Database::pdo()->lastInsertId();
    }

    public function findActive(int $itemType, int $itemId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM trash_items WHERE item_type = ? AND item_id = ? AND purged_at IS NULL');
        $stmt->execute([$itemType, $itemId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM trash_items WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function listActive(): array
    {
        return Database::pdo()->query('SELECT * FROM trash_items WHERE purged_at IS NULL ORDER BY created_at DESC')->fetchAll();
    }

    public function markPurged(int $id): void
    {
        Database::pdo()->prepare('UPDATE trash_items SET purged_at = datetime(\'now\') WHERE id = ?')->execute([$id]);
    }

    public function delete(int $id): void
    {
        Database::pdo()->prepare('DELETE FROM trash_items WHERE id = ?')->execute([$id]);
    }

    public function log(int $trashId, ?int $userId, string $action, bool $success, array $details = []): void
    {
        Database::pdo()->prepare('INSERT INTO trash_log (trash_id, user_id, action, success, details) VALUES (?, ?, ?, ?, ?)')
            ->execute([$trashId, $userId, $action, $success ? 1 : 0, json_encode($details, JSON_UNESCAPED_UNICODE)]);
    }
}