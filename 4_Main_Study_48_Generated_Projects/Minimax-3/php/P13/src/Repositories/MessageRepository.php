<?php
declare(strict_types=1);

namespace MailServer\Repositories;

use MailServer\Database\Database;
use PDO;

class MessageRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function findById(int $userId, int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT m.*, f.name AS folder_name FROM messages m JOIN folders f ON f.id = m.folder_id WHERE m.user_id = ? AND m.id = ?');
        $stmt->execute([$userId, $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function markRead(int $userId, int $id): bool
    {
        $stmt = $this->pdo->prepare('UPDATE messages SET is_read = 1 WHERE user_id = ? AND id = ?');
        return $stmt->execute([$userId, $id]);
    }

    public function toggleStar(int $userId, int $id): ?array
    {
        $stmt = $this->pdo->prepare('UPDATE messages SET is_starred = 1 - is_starred WHERE user_id = ? AND id = ?');
        $stmt->execute([$userId, $id]);
        return $this->findById($userId, $id);
    }

    public function moveToFolder(int $userId, int $id, int $folderId): bool
    {
        $stmt = $this->pdo->prepare('UPDATE messages SET folder_id = ? WHERE user_id = ? AND id = ?');
        return $stmt->execute([$folderId, $userId, $id]);
    }

    public function delete(int $userId, int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM messages WHERE user_id = ? AND id = ?');
        return $stmt->execute([$userId, $id]);
    }

    public function send(int $userId, array $data): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM folders WHERE user_id = ? AND folder_type = "sent"');
        $stmt->execute([$userId]);
        $folder = $stmt->fetch();
        if (!$folder) {
            $folderId = (int) $this->pdo->lastInsertId();
            $stmt2 = $this->pdo->prepare('INSERT INTO folders (user_id, name, folder_type) VALUES (?, ?, "sent")');
            $stmt2->execute([$userId, 'Sent']);
            $folderId = (int) $this->pdo->lastInsertId();
        } else {
            $folderId = (int) $folder['id'];
        }
        $stmt = $this->pdo->prepare('INSERT INTO messages (user_id, folder_id, from_address, from_name, to_addresses, cc_addresses, subject, body_text, body_html, status, is_read, sent_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "sent", 1, CURRENT_TIMESTAMP)');
        $stmt->execute([
            $userId,
            $folderId,
            $data['from_address'],
            $data['from_name'] ?? null,
            $data['to_addresses'],
            $data['cc_addresses'] ?? null,
            $data['subject'],
            $data['body_text'],
            $data['body_html'] ?? null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function saveDraft(int $userId, array $data): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO drafts (user_id, to_addresses, cc_addresses, subject, body_text, body_html) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $userId,
            $data['to_addresses'] ?? null,
            $data['cc_addresses'] ?? null,
            $data['subject'] ?? null,
            $data['body_text'] ?? null,
            $data['body_html'] ?? null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function draftsFor(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM drafts WHERE user_id = ? ORDER BY updated_at DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
}