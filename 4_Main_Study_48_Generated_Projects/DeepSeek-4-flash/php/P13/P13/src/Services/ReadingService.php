<?php

declare(strict_types=1);

namespace P13\Services;

use P13\Database;
use P13\Validation;

/**
 * Message reading service (MAIL-04): read state, thread grouping and
 * per-message detail views.
 */
final class ReadingService
{
    public function __construct(private Database $db)
    {
    }

    public function list(int $userId, ?int $folderId = null, string $q = '', int $limit = 100): array
    {
        $mailbox = new MailboxService($this->db);
        return $mailbox->listMessages($userId, $folderId, $q, $limit);
    }

    public function detail(int $messageId, int $userId): ?array
    {
        $message = $this->db->row(
            'SELECT m.*,
                    (SELECT COUNT(*) FROM stored_files sf WHERE sf.message_id = m.id) AS attachments
               FROM messages m
              WHERE m.id = ? AND m.user_id = ?',
            [$messageId, $userId]
        );
        if ($message === null) {
            return null;
        }
        $message['attachments_list'] = $this->db->select(
            'SELECT id, original_name, mime_type, size FROM stored_files WHERE message_id = ?',
            [$messageId]
        );
        $thread = [];
        if ($message['thread_id'] !== null) {
            $thread = $this->db->select(
                'SELECT id, from_address, from_name, subject, status, created_at
                   FROM messages
                  WHERE thread_id = ? AND user_id = ?
                  ORDER BY id ASC',
                [$message['thread_id'], $userId]
            );
        }
        $message['thread'] = $thread;
        $message['headers_parsed'] = json_decode((string) $message['headers'], true) ?: [];
        return $message;
    }

    /**
     * Record that the user opened a message.
     *
     * @return array{ok: bool, message?: string, errors?: array<string, string>}
     */
    public function markOpened(int $messageId, int $userId): array
    {
        $message = $this->db->row('SELECT * FROM messages WHERE id = ? AND user_id = ?', [$messageId, $userId]);
        if ($message === null) {
            return ['ok' => false, 'errors' => ['message' => 'Unknown or out-of-scope message.']];
        }
        $this->db->execute(
            'INSERT INTO message_reading (user_id, message_id, opened_at, starred) VALUES (?, ?, datetime(\'now\'), 0)',
            [$userId, $messageId]
        );
        $this->db->execute(
            "UPDATE messages SET status = 'read' WHERE id = ?",
            [$messageId]
        );
        return ['ok' => true, 'message' => 'Message marked as read.'];
    }

    /**
     * Update read/starred state.
     *
     * @return array{ok: bool, message?: string, errors?: array<string, string>}
     */
    public function updateState(int $messageId, int $userId, string $status, int $starred): array
    {
        if (!Validation::in($status, ['read', 'unread'])) {
            return ['ok' => false, 'errors' => ['status' => 'Invalid status.']];
        }
        $message = $this->db->row('SELECT * FROM messages WHERE id = ? AND user_id = ?', [$messageId, $userId]);
        if ($message === null) {
            return ['ok' => false, 'errors' => ['message' => 'Unknown or out-of-scope message.']];
        }
        $this->db->execute('UPDATE messages SET status = ? WHERE id = ?', [$status, $messageId]);
        if ($starred === 1) {
            $this->db->execute(
                'INSERT OR IGNORE INTO message_reading (user_id, message_id, opened_at, starred) VALUES (?, ?, datetime(\'now\'), 1)',
                [$userId, $messageId]
            );
            $this->db->execute(
                'UPDATE message_reading SET starred = 1 WHERE user_id = ? AND message_id = ?',
                [$userId, $messageId]
            );
        } else {
            $this->db->execute(
                'UPDATE message_reading SET starred = 0 WHERE user_id = ? AND message_id = ?',
                [$userId, $messageId]
            );
        }
        return ['ok' => true, 'message' => 'Reading state updated.'];
    }
}
