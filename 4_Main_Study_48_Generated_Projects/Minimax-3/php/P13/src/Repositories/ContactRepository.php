<?php
declare(strict_types=1);

namespace MailServer\Repositories;

use MailServer\Database\Database;
use PDO;

class ContactRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function listFor(int $userId, string $search = null): array
    {
        if ($search) {
            $like = '%' . $search . '%';
            $stmt = $this->pdo->prepare('SELECT * FROM contacts WHERE user_id = ? AND (name LIKE ? OR email LIKE ?) ORDER BY name');
            $stmt->execute([$userId, $like, $like]);
        } else {
            $stmt = $this->pdo->prepare('SELECT * FROM contacts WHERE user_id = ? ORDER BY name');
            $stmt->execute([$userId]);
        }
        return $stmt->fetchAll();
    }

    public function find(int $userId, int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM contacts WHERE user_id = ? AND id = ?');
        $stmt->execute([$userId, $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(int $userId, array $data): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO contacts (user_id, name, email, organization, phone, notes) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $userId,
            $data['name'],
            $data['email'],
            $data['organization'] ?? null,
            $data['phone'] ?? null,
            $data['notes'] ?? null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $userId, int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare('UPDATE contacts SET name = ?, email = ?, organization = ?, phone = ?, notes = ? WHERE user_id = ? AND id = ?');
        return $stmt->execute([
            $data['name'],
            $data['email'],
            $data['organization'] ?? null,
            $data['phone'] ?? null,
            $data['notes'] ?? null,
            $userId,
            $id,
        ]);
    }

    public function delete(int $userId, int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM contacts WHERE user_id = ? AND id = ?');
        return $stmt->execute([$userId, $id]);
    }

    public function bulkCreate(int $userId, array $records): int
    {
        $count = 0;
        foreach ($records as $r) {
            if (empty($r['email']) || empty($r['name'])) {
                continue;
            }
            $stmt = $this->pdo->prepare('SELECT id FROM contacts WHERE user_id = ? AND email = ?');
            $stmt->execute([$userId, $r['email']]);
            if ($stmt->fetch()) {
                continue;
            }
            $stmt = $this->pdo->prepare('INSERT INTO contacts (user_id, name, email, organization, phone, notes) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$userId, $r['name'], $r['email'], $r['organization'] ?? null, $r['phone'] ?? null, $r['notes'] ?? null]);
            $count++;
        }
        return $count;
    }
}