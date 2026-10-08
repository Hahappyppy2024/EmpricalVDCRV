<?php

declare(strict_types=1);

namespace P13\Services;

use P13\Database;

/**
 * Mailbox overview service (MAIL-02): folders, unread counts, message
 * listing and search.
 */
final class MailboxService
{
    public function __construct(private Database $db)
    {
    }

    public function overview(int $userId, ?int $folderId = null, string $q = ''): array
    {
        $folders = $this->folders($userId);
        $messages = $this->listMessages($userId, $folderId, $q, 100);
        return [
            'folders' => $folders,
            'messages' => $messages['items'],
            'total' => $messages['total'],
            'query' => $q,
            'folder_id' => $folderId,
            'unread_total' => $this->unreadTotal($userId),
        ];
    }

    public function folders(int $userId): array
    {
        return $this->db->select(
            'SELECT f.id, f.name, f.is_system,
                    (SELECT COUNT(*) FROM messages m WHERE m.folder_id = f.id) AS total,
                    (SELECT COUNT(*) FROM messages m WHERE m.folder_id = f.id AND m.status = \'unread\') AS unread
               FROM folders f
              WHERE f.user_id = ?
              ORDER BY f.is_system DESC, f.name ASC',
            [$userId]
        );
    }

    public function unreadTotal(int $userId): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM messages WHERE user_id = ? AND status = 'unread'",
            [$userId]
        );
    }

    public function listMessages(int $userId, ?int $folderId, string $q, int $limit = 100): array
    {
        $where = ['m.user_id = ?'];
        $params = [$userId];
        if ($folderId !== null) {
            $f = $this->db->row('SELECT id FROM folders WHERE id = ? AND user_id = ?', [$folderId, $userId]);
            if ($f === null) {
                return ['items' => [], 'total' => 0];
            }
            $where[] = 'm.folder_id = ?';
            $params[] = $folderId;
        }
        if ($q !== '') {
            $where[] = '(m.subject LIKE ? OR m.body LIKE ? OR m.from_address LIKE ?)';
            $like = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        $clause = implode(' AND ', $where);
        $total = (int) $this->db->scalar('SELECT COUNT(*) FROM messages m WHERE ' . $clause, $params);
        $items = $this->db->select(
            "SELECT m.id, m.folder_id, m.from_address, m.from_name, m.to_address, m.subject, m.body,
                    m.thread_id, m.priority, m.status, m.created_at,
                    (SELECT COUNT(*) FROM stored_files sf WHERE sf.message_id = m.id) AS attachments
               FROM messages m
              WHERE {$clause}
              ORDER BY m.id DESC
              LIMIT ?",
            array_merge($params, [$limit])
        );
        return ['items' => $items, 'total' => $total];
    }

    /**
     * @return array{ok: bool, id?: int, folder?: array<string, mixed>, errors?: array<string, string>}
     */
    public function createFolder(int $userId, string $name): array
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 64) {
            return ['ok' => false, 'errors' => ['name' => 'Folder name is required (max 64 chars).']];
        }
        $exists = $this->db->row(
            'SELECT id FROM folders WHERE user_id = ? AND name = ?',
            [$userId, $name]
        );
        if ($exists !== null) {
            return ['ok' => false, 'errors' => ['name' => 'A folder with this name already exists.']];
        }
        $this->db->execute(
            'INSERT INTO folders (user_id, name, is_system, created_at) VALUES (?, ?, 0, datetime(\'now\'))',
            [$userId, $name]
        );
        $id = $this->db->lastInsertId();
        $folder = $this->db->row('SELECT * FROM folders WHERE id = ?', [$id]);
        return ['ok' => true, 'id' => $id, 'folder' => $folder];
    }

    /**
     * @return array{ok: bool, folder?: array<string, mixed>, errors?: array<string, string>}
     */
    public function renameFolder(int $folderId, int $userId, string $name): array
    {
        $folder = $this->db->row('SELECT * FROM folders WHERE id = ? AND user_id = ?', [$folderId, $userId]);
        if ($folder === null) {
            return ['ok' => false, 'errors' => ['folder' => 'Unknown or out-of-scope folder.']];
        }
        if ((int) $folder['is_system'] === 1) {
            return ['ok' => false, 'errors' => ['folder' => 'System folders cannot be renamed.']];
        }
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 64) {
            return ['ok' => false, 'errors' => ['name' => 'Folder name is required (max 64 chars).']];
        }
        $this->db->execute('UPDATE folders SET name = ? WHERE id = ?', [$name, $folderId]);
        $updated = $this->db->row('SELECT * FROM folders WHERE id = ?', [$folderId]);
        return ['ok' => true, 'folder' => $updated];
    }
}
