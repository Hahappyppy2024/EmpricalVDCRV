<?php
declare(strict_types=1);
namespace App\Repository;

use App\Infrastructure\Database;

final class FolderRepository
{
    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM folders WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = Database::pdo()->prepare('INSERT INTO folders (name, owner_id, team_id, parent_id, status) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([
            $data['name'],
            $data['owner_id'] ?? null,
            $data['team_id'] ?? null,
            $data['parent_id'] ?? null,
            $data['status'] ?? 'active',
        ]);
        return (int)Database::pdo()->lastInsertId();
    }

    public function rename(int $id, string $name): void
    {
        Database::pdo()->prepare('UPDATE folders SET name = ?, updated_at = datetime(\'now\') WHERE id = ?')
            ->execute([$name, $id]);
    }

    public function move(int $id, ?int $parentId, ?int $ownerId, ?int $teamId): void
    {
        Database::pdo()->prepare('UPDATE folders SET parent_id = ?, owner_id = ?, team_id = ?, updated_at = datetime(\'now\') WHERE id = ?')
            ->execute([$parentId, $ownerId, $teamId, $id]);
    }

    public function setStatus(int $id, string $status): void
    {
        Database::pdo()->prepare('UPDATE folders SET status = ?, deleted_at = CASE WHEN ? = \'trashed\' THEN datetime(\'now\') ELSE NULL END, updated_at = datetime(\'now\') WHERE id = ?')
            ->execute([$status, $status, $id]);
    }

    public function listChildren(int $parentId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM folders WHERE parent_id = ? AND status = \'active\' ORDER BY name');
        $stmt->execute([$parentId]);
        return $stmt->fetchAll();
    }

    public function listRootForUser(int $userId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM folders WHERE owner_id = ? AND parent_id IS NULL AND status = \'active\' ORDER BY name');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function listRootForTeam(int $teamId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM folders WHERE team_id = ? AND parent_id IS NULL AND status = \'active\' ORDER BY name');
        $stmt->execute([$teamId]);
        return $stmt->fetchAll();
    }

    public function listAccessibleForUser(int $userId, array $teamIds): array
    {
        if ($teamIds === []) {
            $stmt = Database::pdo()->prepare('SELECT * FROM folders WHERE owner_id = ? AND status = \'active\' ORDER BY name');
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
        }
        $placeholders = implode(',', array_fill(0, count($teamIds), '?'));
        $sql = "SELECT * FROM folders WHERE (owner_id = ? OR team_id IN ($placeholders)) AND status = 'active' ORDER BY name";
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute(array_merge([$userId], $teamIds));
        return $stmt->fetchAll();
    }

    public function descendantFolders(int $folderId): array
    {
        $stmt = Database::pdo()->prepare("WITH RECURSIVE descendants(id) AS (SELECT id FROM folders WHERE parent_id = ? UNION ALL SELECT f.id FROM folders f JOIN descendants d ON f.parent_id = d.id) SELECT * FROM folders WHERE id IN (SELECT id FROM descendants)");
        $stmt->execute([$folderId]);
        return $stmt->fetchAll();
    }
}