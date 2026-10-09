<?php
declare(strict_types=1);

namespace MailServer\Repositories;

use MailServer\Database\Database;
use PDO;

class MailboxRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function foldersForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT f.*, (SELECT COUNT(*) FROM messages m WHERE m.folder_id = f.id) AS computed_total, (SELECT COUNT(*) FROM messages m WHERE m.folder_id = f.id AND m.is_read = 0) AS computed_unread FROM folders f WHERE f.user_id = ? ORDER BY CASE f.folder_type WHEN "inbox" THEN 0 WHEN "sent" THEN 1 WHEN "drafts" THEN 2 WHEN "trash" THEN 3 WHEN "spam" THEN 4 WHEN "archive" THEN 5 ELSE 6 END, f.name');
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $this->pdo->prepare('UPDATE folders SET unread_count = ?, total_count = ? WHERE id = ?')->execute([$r['computed_unread'], $r['computed_total'], $r['id']]);
            $r['unread_count'] = (int) $r['computed_unread'];
            $r['total_count'] = (int) $r['computed_total'];
        }
        return $rows;
    }

    public function folderById(int $userId, int $folderId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM folders WHERE id = ? AND user_id = ?');
        $stmt->execute([$folderId, $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function createFolder(int $userId, string $name, string $type = 'custom'): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO folders (user_id, name, folder_type) VALUES (?, ?, ?)');
        $stmt->execute([$userId, $name, $type]);
        return (int) $this->pdo->lastInsertId();
    }

    public function messagesInFolder(int $userId, int $folderId, int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare('SELECT m.*, f.name AS folder_name FROM messages m JOIN folders f ON f.id = m.folder_id WHERE m.user_id = ? AND m.folder_id = ? ORDER BY m.created_at DESC LIMIT ? OFFSET ?');
        $stmt->execute([$userId, $folderId, $limit, $offset]);
        return $stmt->fetchAll();
    }

    public function search(int $userId, string $query): array
    {
        $like = '%' . $query . '%';
        $stmt = $this->pdo->prepare('SELECT m.*, f.name AS folder_name FROM messages m JOIN folders f ON f.id = m.folder_id WHERE m.user_id = ? AND (m.subject LIKE ? OR m.body_text LIKE ? OR m.from_address LIKE ?) ORDER BY m.created_at DESC LIMIT 50');
        $stmt->execute([$userId, $like, $like, $like]);
        return $stmt->fetchAll();
    }
}