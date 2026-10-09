<?php
declare(strict_types=1);

namespace LMS\Repository;

use PDO;

final class ExportRepository
{
    public function __construct(private PDO $pdo) {}

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM exports WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function listRecent(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT e.*, u.full_name AS requester_name, c.title AS course_title
             FROM exports e
             JOIN users u ON u.id = e.requested_by
             LEFT JOIN courses c ON c.id = e.course_id
             ORDER BY e.created_at DESC LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function record(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO exports (course_id, requested_by, format, status, file_path, summary)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['course_id'] ?? null,
            $data['requested_by'],
            $data['format'],
            $data['status'] ?? 'ready',
            $data['file_path'],
            $data['summary'] ?? '',
        ]);
        return (int)$this->pdo->lastInsertId();
    }
}
