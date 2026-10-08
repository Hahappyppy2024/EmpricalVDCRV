<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;

/**
 * Users, sessions, password resets and account access log.
 */
final class UserRepository
{
    public function __construct(private Database $db)
    {
    }

    public function find(int $id): ?array
    {
        return $this->db->first('SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?', [$id]);
    }

    public function findActive(int $id): ?array
    {
        return $this->db->first('SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? AND u.active = 1', [$id]);
    }

    public function findByIdentifier(string $usernameOrEmail): ?array
    {
        return $this->db->first('SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.username = ? OR u.email = ?', [$usernameOrEmail, $usernameOrEmail]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(string $roleFilter = '', string $q = ''): array
    {
        $sql = 'SELECT u.id, u.username, u.email, u.display_name, u.active, u.created_at, r.name AS role_name
                FROM users u JOIN roles r ON r.id = u.role_id WHERE 1 = 1';
        $params = [];
        if ($roleFilter !== '' && $roleFilter !== 'all') {
            $sql .= ' AND r.name = ?';
            $params[] = $roleFilter;
        }
        if ($q !== '') {
            $sql .= ' AND (u.username LIKE ? OR u.email LIKE ? OR u.display_name LIKE ?)';
            $like = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        $sql .= ' ORDER BY u.id ASC';
        return $this->db->select($sql, $params);
    }

    public function create(array $data): int
    {
        return $this->db->insert('users', $data);
    }

    public function update(int $id, array $data): void
    {
        $this->db->update('users', $data, 'id = :id', ['id' => $id]);
    }

    public function roleIdByName(string $name): ?int
    {
        $row = $this->db->first('SELECT id FROM roles WHERE name = ?', [$name]);
        return $row === null ? null : (int) $row['id'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function accessLog(int $userId, bool $all = false): array
    {
        if ($all) {
            return $this->db->select('SELECT a.*, COALESCE(u.username, "system") AS username FROM account_access_log a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.id DESC LIMIT 200');
        }
        return $this->db->select('SELECT * FROM account_access_log WHERE user_id = ? ORDER BY id DESC LIMIT 50', [$userId]);
    }
}
