<?php

declare(strict_types=1);

namespace P13\Services;

use P13\Audit;
use P13\Database;
use P13\Storage;
use P13\Validation;

/**
 * Attachment handling service (MAIL-05): uploads, listing and downloads.
 * Files are stored locally with metadata linking them to the owning user.
 */
final class AttachmentService
{
    public function __construct(private Database $db, private Storage $storage, private Audit $audit)
    {
    }

    public function list(int $userId): array
    {
        $items = $this->db->select(
            'SELECT a.id AS attachment_id, a.status AS attachment_status, a.created_at,
                    f.id AS file_id, f.original_name, f.mime_type, f.size,
                    m.subject AS message_subject, m.from_address AS message_from, m.id AS message_id
               FROM attachment_handling a
               JOIN stored_files f ON f.id = a.stored_file_id
               LEFT JOIN messages m ON m.id = a.message_id
              WHERE a.user_id = ?
              ORDER BY a.id DESC',
            [$userId]
        );
        return ['items' => $items, 'total' => count($items)];
    }

    /**
     * @return array{ok: bool, id?: int, file?: array<string, mixed>, errors?: array<string, string>}
     */
    public function upload(int $userId, \Psr\Http\Message\UploadedFileInterface $file, ?int $messageId = null): array
    {
        $stored = $this->storage->storeUpload($file, $userId, 'attachment', $messageId);
        if (!$stored['ok']) {
            return ['ok' => false, 'errors' => ['file' => $stored['error']]];
        }
        $this->db->execute(
            'INSERT INTO attachment_handling (user_id, message_id, stored_file_id, status, created_at)
             VALUES (?, ?, ?, \'linked\', datetime(\'now\'))',
            [$userId, $messageId, $stored['id']]
        );
        $this->audit->log($userId, null, null, 'attachment.upload', 'StoredFile', (string) $stored['id'], [
            'original_name' => $stored['file']['original_name'],
            'size' => $stored['file']['size'],
        ]);
        return ['ok' => true, 'id' => $stored['id'], 'file' => $stored['file']];
    }

    /**
     * Update attachment metadata (only the handling status is mutable).
     *
     * @return array{ok: bool, attachment?: array<string, mixed>, errors?: array<string, string>}
     */
    public function update(int $attachmentId, int $userId, string $status): array
    {
        if (!Validation::in($status, ['pending', 'linked', 'deleted'])) {
            return ['ok' => false, 'errors' => ['status' => 'Invalid attachment status.']];
        }
        $row = $this->db->row(
            'SELECT * FROM attachment_handling WHERE id = ? AND user_id = ?',
            [$attachmentId, $userId]
        );
        if ($row === null) {
            return ['ok' => false, 'errors' => ['attachment' => 'Unknown or out-of-scope attachment.']];
        }
        $this->db->execute('UPDATE attachment_handling SET status = ? WHERE id = ?', [$status, $attachmentId]);
        $updated = $this->db->row('SELECT * FROM attachment_handling WHERE id = ?', [$attachmentId]);
        return ['ok' => true, 'attachment' => $updated];
    }

    /**
     * Resolve a file for download, enforcing ownership (MAIL-05-FA-03).
     *
     * @return array{file: array<string, mixed>, path: string}|null
     */
    public function download(int $fileId, int $userId): ?array
    {
        return $this->storage->pathFor($fileId, $userId);
    }
}
