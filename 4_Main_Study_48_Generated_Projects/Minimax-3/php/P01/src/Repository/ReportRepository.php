<?php
declare(strict_types=1);

namespace LMS\Repository;

use PDO;

final class ReportRepository
{
    public function __construct(private PDO $pdo) {}

    public function record(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO reports (requested_by, scope, filters_json, file_path, summary)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['requested_by'],
            $data['scope'],
            json_encode($data['filters'] ?? []),
            $data['file_path'],
            $data['summary'] ?? '',
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function listRecent(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.*, u.full_name AS requester_name FROM reports r
             JOIN users u ON u.id = r.requested_by
             ORDER BY r.created_at DESC LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM reports WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
