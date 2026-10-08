<?php

declare(strict_types=1);

namespace CloudFS\Repositories;

use CloudFS\Database\Database;

final class TeamRepository
{
    public function __construct(private Database $db)
    {
    }

    public function listForUser(int $userId): array
    {
        return $this->db->all(
            'SELECT t.*, tm.role AS membership_role,
                    (SELECT COUNT(*) FROM team_members m WHERE m.team_id = t.id) AS member_count
             FROM teams t JOIN team_members tm ON tm.team_id = t.id
             WHERE tm.user_id = ? ORDER BY t.name',
            [$userId]
        );
    }

    public function listAll(): array
    {
        return $this->db->all('SELECT * FROM teams ORDER BY name');
    }

    public function find(int $teamId): ?array
    {
        return $this->db->one('SELECT * FROM teams WHERE id = ?', [$teamId]);
    }

    public function membership(int $teamId, int $userId): ?array
    {
        return $this->db->one('SELECT * FROM team_members WHERE team_id = ? AND user_id = ?', [$teamId, $userId]);
    }

    public function create(string $name, string $description, int $creatorId): array
    {
        $existing = $this->db->one('SELECT id FROM teams WHERE name = ?', [$name]);
        if ($existing) {
            return ['ok' => false, 'errors' => ['A team with that name already exists.']];
        }
        $id = $this->db->insert(
            'INSERT INTO teams (name, description, created_by, created_at) VALUES (?, ?, ?, datetime(\'now\'))',
            [$name, $description, $creatorId]
        );
        $this->db->insert('INSERT INTO team_members (team_id, user_id, role, created_at) VALUES (?, ?, \'owner\', datetime(\'now\'))', [$id, $creatorId]);
        return ['ok' => true, 'id' => $id];
    }

    public function update(int $teamId, string $name, string $description): array
    {
        $existing = $this->db->one('SELECT id FROM teams WHERE name = ? AND id <> ?', [$name, $teamId]);
        if ($existing) {
            return ['ok' => false, 'errors' => ['A team with that name already exists.']];
        }
        $this->db->run('UPDATE teams SET name = ?, description = ? WHERE id = ?', [$name, $description, $teamId]);
        return ['ok' => true];
    }

    public function members(int $teamId): array
    {
        return $this->db->all(
            'SELECT tm.*, u.username, u.email, u.full_name
             FROM team_members tm JOIN users u ON u.id = tm.user_id
             WHERE tm.team_id = ? ORDER BY tm.role, u.username',
            [$teamId]
        );
    }

    public function addMember(int $teamId, int $userId, string $role): array
    {
        $existing = $this->db->one('SELECT id FROM team_members WHERE team_id = ? AND user_id = ?', [$teamId, $userId]);
        if ($existing) {
            return ['ok' => false, 'errors' => ['That user is already a member of this team.']];
        }
        $this->db->insert(
            'INSERT INTO team_members (team_id, user_id, role, created_at) VALUES (?, ?, ?, datetime(\'now\'))',
            [$teamId, $userId, $role]
        );
        return ['ok' => true];
    }

    public function removeMember(int $teamId, int $userId): array
    {
        $owners = $this->db->value('SELECT COUNT(*) FROM team_members WHERE team_id = ? AND role = \'owner\'', [$teamId]);
        $target = $this->db->one('SELECT * FROM team_members WHERE team_id = ? AND user_id = ?', [$teamId, $userId]);
        if (!$target) {
            return ['ok' => false, 'errors' => ['That user is not a member of this team.']];
        }
        if ($target['role'] === 'owner' && $owners <= 1) {
            return ['ok' => false, 'errors' => ['A team must keep at least one owner.']];
        }
        $this->db->run('DELETE FROM team_members WHERE team_id = ? AND user_id = ?', [$teamId, $userId]);
        return ['ok' => true];
    }

    public function folders(int $teamId): array
    {
        return $this->db->all(
            'SELECT tf.id AS team_folder_id, f.*, u.username AS owner_name
             FROM team_folders tf JOIN folders f ON f.id = tf.folder_id
             JOIN users u ON u.id = f.owner_id
             WHERE tf.team_id = ? ORDER BY f.name',
            [$teamId]
        );
    }

    public function addFolder(int $teamId, int $folderId): array
    {
        $this->db->insert('INSERT OR IGNORE INTO team_folders (team_id, folder_id, created_at) VALUES (?, ?, datetime(\'now\'))', [$teamId, $folderId]);
        return ['ok' => true];
    }

    public function filesInFolders(array $folderIds): array
    {
        if (!$folderIds) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($folderIds), '?'));
        return $this->db->all(
            'SELECT * FROM files WHERE folder_id IN (' . $placeholders . ') AND status = \'active\' ORDER BY name',
            $folderIds
        );
    }
}
