<?php
declare(strict_types=1);

namespace MailServer\Repositories;

use MailServer\Database\Database;
use PDO;

class FileRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function store(int $userId, string $filename, string $mimeType, int $size, string $storagePath, ?int $messageId = null): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO attachments (message_id, user_id, filename, mime_type, size_bytes, storage_path) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$messageId, $userId, $filename, $mimeType, $size, $storagePath]);
        return (int) $this->pdo->lastInsertId();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM attachments WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function listFor(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM attachments WHERE user_id = ? ORDER BY created_at DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function forMessage(int $messageId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM attachments WHERE message_id = ?');
        $stmt->execute([$messageId]);
        return $stmt->fetchAll();
    }

    public function delete(int $userId, int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM attachments WHERE user_id = ? AND id = ?');
        return $stmt->execute([$userId, $id]);
    }

    public function createJob(int $userId, string $kind, string $format, string $filename, string $storagePath, string $status = 'completed', int $rowCount = 0, ?string $error = null): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO import_export_jobs (user_id, kind, payload_format, filename, storage_path, status, row_count, error_message) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$userId, $kind, $format, $filename, $storagePath, $status, $rowCount, $error]);
        return (int) $this->pdo->lastInsertId();
    }

    public function jobsFor(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM import_export_jobs WHERE user_id = ? ORDER BY created_at DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function findJob(int $userId, int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM import_export_jobs WHERE user_id = ? AND id = ?');
        $stmt->execute([$userId, $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}