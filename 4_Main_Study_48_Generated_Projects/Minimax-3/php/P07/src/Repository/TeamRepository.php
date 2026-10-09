<?php
declare(strict_types=1);
namespace App\Repository;

use App\Infrastructure\Database;

final class TeamRepository
{
    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM team_spaces WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = Database::pdo()->prepare('INSERT INTO team_spaces (name, description, created_by, root_folder_id, quota_limit, used_bytes) VALUES (?, ?, ?, ?, ?, 0)');
        $stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            $data['created_by'],
            $data['root_folder_id'] ?? null,
            $data['quota_limit'] ?? 1073741824,
        ]);
        return (int)Database::pdo()->lastInsertId();
    }

    public function setRootFolder(int $teamId, int $folderId): void
    {
        Database::pdo()->prepare('UPDATE team_spaces SET root_folder_id = ? WHERE id = ?')->execute([$folderId, $teamId]);
    }

    public function addMember(int $teamId, int $userId, string $role = 'editor'): void
    {
        Database::pdo()->prepare('INSERT OR REPLACE INTO team_memberships (team_id, user_id, role, status) VALUES (?, ?, ?, \'active\')')
            ->execute([$teamId, $userId, $role]);
    }

    public function removeMember(int $teamId, int $userId): void
    {
        Database::pdo()->prepare('DELETE FROM team_memberships WHERE team_id = ? AND user_id = ?')->execute([$teamId, $userId]);
    }

    public function updateMemberRole(int $teamId, int $userId, string $role): void
    {
        Database::pdo()->prepare('UPDATE team_memberships SET role = ? WHERE team_id = ? AND user_id = ?')->execute([$role, $teamId, $userId]);
    }

    public function membership(int $teamId, int $userId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM team_memberships WHERE team_id = ? AND user_id = ?');
        $stmt->execute([$teamId, $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function members(int $teamId): array
    {
        $stmt = Database::pdo()->prepare('SELECT u.id, u.email, u.name, m.role FROM team_memberships m JOIN users u ON u.id = m.user_id WHERE m.team_id = ? ORDER BY u.email');
        $stmt->execute([$teamId]);
        return $stmt->fetchAll();
    }

    public function listForUser(int $userId): array
    {
        $stmt = Database::pdo()->prepare('SELECT t.* FROM team_spaces t JOIN team_memberships m ON m.team_id = t.id WHERE m.user_id = ? ORDER BY t.name');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function listAll(): array
    {
        return Database::pdo()->query('SELECT * FROM team_spaces ORDER BY name')->fetchAll();
    }

    public function update(int $id, array $fields): void
    {
        $allowed = ['name', 'description', 'quota_limit'];
        $sets = [];
        $params = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $fields)) {
                $sets[] = "$key = ?";
                $params[] = $fields[$key];
            }
        }
        if ($sets === []) {
            return;
        }
        $params[] = $id;
        Database::pdo()->prepare('UPDATE team_spaces SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($params);
    }

    public function delete(int $id): void
    {
        Database::pdo()->prepare('DELETE FROM team_spaces WHERE id = ?')->execute([$id]);
    }

    public function adjustUsed(int $teamId, int $delta): void
    {
        Database::pdo()->prepare('UPDATE team_spaces SET used_bytes = used_bytes + ? WHERE id = ?')->execute([$delta, $teamId]);
    }

    public function log(int $teamId, ?int $userId, string $action, bool $success, array $details = []): void
    {
        Database::pdo()->prepare('INSERT INTO team_spaces_log (team_id, user_id, action, success, details) VALUES (?, ?, ?, ?, ?)')
            ->execute([$teamId, $userId, $action, $success ? 1 : 0, json_encode($details, JSON_UNESCAPED_UNICODE)]);
    }
}