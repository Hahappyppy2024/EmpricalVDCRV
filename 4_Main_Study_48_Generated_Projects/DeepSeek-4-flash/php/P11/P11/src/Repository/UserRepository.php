<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class UserRepository extends BaseRepository
{
    public function findByUsernameOrEmail(string $identifier): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1');
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public function existsUsername(string $username): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
        $stmt->execute([$username]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function existsEmail(string $email): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
        $stmt->execute([$email]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function create(string $username, string $email, string $passwordHash, string $fullName, string $role, ?int $planId): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO users (username, email, password_hash, full_name, role, plan_id, created_at) VALUES (?,?,?,?,?,?,?)'
        );
        $stmt->execute([$username, $email, $passwordHash, $fullName, $role, $planId, date('Y-m-d H:i:s')]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $fields): void
    {
        $allowed = ['full_name', 'email', 'role', 'plan_id', 'password_hash'];
        $sets = [];
        $values = [];
        foreach ($fields as $key => $value) {
            if (in_array($key, $allowed, true)) {
                $sets[] = $key . ' = ?';
                $values[] = $value;
            }
        }
        if ($sets === []) {
            return;
        }
        $values[] = $id;
        $this->db->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($values);
    }

    public function all(): array
    {
        $rows = $this->db->query('SELECT u.*, p.name AS plan_name, p.disk_quota, p.bandwidth_quota, p.max_domains, p.max_sites, p.max_databases FROM users u LEFT JOIN plans p ON p.id = u.plan_id ORDER BY u.id')->fetchAll();
        foreach ($rows as &$row) {
            $row['domains_count'] = $this->countFor('domains', $row['id']);
            $row['sites_count'] = $this->countFor('sites', $row['id']);
            $row['databases_count'] = $this->countFor('databases', $row['id']);
        }

        return $rows;
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    }

    public function count(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    private function countFor(string $table, int $userId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$table} WHERE user_id = ?");
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }
}
