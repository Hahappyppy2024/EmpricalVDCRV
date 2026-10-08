<?php

declare(strict_types=1);

namespace P13\Services;

use P13\Audit;
use P13\Database;
use P13\Validation;

/**
 * Message compose service (MAIL-03): drafts and simulated local delivery.
 *
 * Delivery is a deterministic local adapter: sending creates a copy in the
 * recipient's Inbox and a copy in the sender's Sent folder. No external
 * SMTP is contacted, so the application runs fully offline.
 */
final class ComposeService
{
    public function __construct(private Database $db, private Audit $audit)
    {
    }

    public function list(int $userId): array
    {
        $items = $this->db->select(
            'SELECT c.id, c.recipient, c.subject, c.body, c.status, c.message_id, c.created_at, c.updated_at
               FROM message_compose c
              WHERE c.user_id = ?
              ORDER BY c.id DESC',
            [$userId]
        );
        return ['items' => $items, 'total' => count($items)];
    }

    /**
     * @return array{ok: bool, id?: int, compose?: array<string, mixed>, errors?: array<string, string>}
     */
    public function saveDraft(int $userId, string $recipient, string $subject, string $body, ?int $draftId = null): array
    {
        $errors = Validation::required(['recipient' => $recipient, 'subject' => $subject], ['recipient', 'subject']);
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }
        if (!Validation::stringLen($body, 10000)) {
            return ['ok' => false, 'errors' => ['body' => 'Body is too long (max 10000 chars).']];
        }
        if ($draftId !== null) {
            $exists = $this->db->row('SELECT id FROM message_compose WHERE id = ? AND user_id = ?', [$draftId, $userId]);
            if ($exists === null) {
                return ['ok' => false, 'errors' => ['draft' => 'Unknown or out-of-scope draft.']];
            }
            $this->db->execute(
                'UPDATE message_compose SET recipient = ?, subject = ?, body = ?, updated_at = datetime(\'now\') WHERE id = ?',
                [$recipient, $subject, $body, $draftId]
            );
            $id = $draftId;
        } else {
            $this->db->execute(
                'INSERT INTO message_compose (user_id, recipient, subject, body, status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, \'draft\', datetime(\'now\'), datetime(\'now\'))',
                [$userId, $recipient, $subject, $body]
            );
            $id = $this->db->lastInsertId();
        }
        $compose = $this->db->row('SELECT * FROM message_compose WHERE id = ?', [$id]);
        return ['ok' => true, 'id' => $id, 'compose' => $compose];
    }

    /**
     * Send a simulated message. Requires at least one valid recipient; the
     * local adapter rejects unknown/invalid recipients deterministically.
     *
     * @return array{ok: bool, id?: int, message?: string, message_id?: int, errors?: array<string, string>}
     */
    public function send(int $userId, string $recipient, string $subject, string $body, ?int $draftId = null, array $attachmentIds = []): array
    {
        $errors = Validation::required(['recipient' => $recipient, 'subject' => $subject], ['recipient', 'subject']);
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }
        $recipients = array_values(array_filter(array_map('trim', explode(',', $recipient)), fn ($r) => $r !== ''));
        if ($recipients === []) {
            return ['ok' => false, 'errors' => ['recipient' => 'At least one recipient is required.']];
        }
        foreach ($recipients as $r) {
            if (!Validation::email($r)) {
                return ['ok' => false, 'errors' => ['recipient' => "Invalid recipient address: {$r}"]];
            }
        }
        if (!Validation::stringLen($body, 10000)) {
            return ['ok' => false, 'errors' => ['body' => 'Body is too long (max 10000 chars).']];
        }

        $sender = $this->db->row('SELECT * FROM users WHERE id = ?', [$userId]);
        if ($sender === null) {
            return ['ok' => false, 'errors' => ['account' => 'Unknown sender account.']];
        }

        $sentIds = [];
        foreach ($recipients as $r) {
            $target = $this->db->row(
                'SELECT * FROM users WHERE email = ? AND status = \'active\'',
                [$r]
            );
            if ($target === null) {
                return ['ok' => false, 'errors' => ['recipient' => "Delivery failed: no mailbox for {$r}"]];
            }
            $folder = $this->db->row(
                'SELECT id FROM folders WHERE user_id = ? AND name = \'Inbox\'',
                [(int) $target['id']]
            );
            if ($folder !== null) {
                $this->db->execute(
                    'INSERT INTO messages (user_id, folder_id, from_address, from_name, to_address, subject, body, headers, thread_id, priority, status, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, \'normal\', \'unread\', datetime(\'now\'))',
                    [
                        (int) $target['id'],
                        (int) $folder['id'],
                        (string) $sender['email'],
                        (string) $sender['display_name'],
                        $r,
                        $subject,
                        $body,
                        json_encode(['delivered' => 'local', 'compose_id' => $draftId]),
                    ]
                );
                $sentIds[] = $this->db->lastInsertId();
            }
        }

        $sentFolder = $this->db->row(
            'SELECT id FROM folders WHERE user_id = ? AND name = \'Sent\'',
            [$userId]
        );
        $sentMessageId = null;
        if ($sentFolder !== null) {
            $this->db->execute(
                'INSERT INTO messages (user_id, folder_id, from_address, from_name, to_address, subject, body, headers, thread_id, priority, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, \'normal\', \'read\', datetime(\'now\'))',
                [
                    $userId,
                    (int) $sentFolder['id'],
                    (string) $sender['email'],
                    (string) $sender['display_name'],
                    implode(', ', $recipients),
                    $subject,
                    $body,
                    json_encode(['delivered' => 'local']),
                ]
            );
            $sentMessageId = $this->db->lastInsertId();
        }

        if ($draftId !== null) {
            $this->db->execute(
                'UPDATE message_compose SET status = \'sent\', message_id = ?, recipient = ?, updated_at = datetime(\'now\') WHERE id = ? AND user_id = ?',
                [$sentMessageId, $recipient, $draftId, $userId]
            );
            $composeId = $draftId;
        } else {
            $this->db->execute(
                'INSERT INTO message_compose (user_id, recipient, subject, body, status, message_id, created_at, updated_at)
                 VALUES (?, ?, ?, ?, \'sent\', ?, datetime(\'now\'), datetime(\'now\'))',
                [$userId, $recipient, $subject, $body, $sentMessageId]
            );
            $composeId = $this->db->lastInsertId();
        }

        foreach ($attachmentIds as $aid) {
            $owned = $this->db->row('SELECT * FROM stored_files WHERE id = ? AND owner_user_id = ?', [(int) $aid, $userId]);
            if ($owned !== null) {
                $this->db->execute(
                    'UPDATE stored_files SET message_id = ? WHERE id = ?',
                    [$sentMessageId, (int) $aid]
                );
                $this->db->execute(
                    'INSERT INTO attachment_handling (user_id, message_id, stored_file_id, status, created_at)
                     VALUES (?, ?, ?, \'linked\', datetime(\'now\'))',
                    [$userId, $sentMessageId, (int) $aid]
                );
            }
        }

        $this->audit->log($userId, (string) $sender['username'], (string) $sender['role'], 'mail.send', 'Message', (string) $sentMessageId, [
            'to' => $recipients,
            'compose_id' => $composeId,
        ]);

        return ['ok' => true, 'id' => $composeId, 'message_id' => $sentMessageId, 'message' => 'Message delivered to local mailbox.'];
    }

    /**
     * @return array{ok: bool, compose?: array<string, mixed>, errors?: array<string, string>}
     */
    public function update(int $draftId, int $userId, string $recipient, string $subject, string $body): array
    {
        return $this->saveDraft($userId, $recipient, $subject, $body, $draftId);
    }
}
