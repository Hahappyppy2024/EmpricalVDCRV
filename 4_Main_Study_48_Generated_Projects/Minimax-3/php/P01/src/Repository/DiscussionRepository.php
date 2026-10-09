<?php
declare(strict_types=1);

namespace LMS\Repository;

use PDO;

final class DiscussionRepository
{
    public function __construct(private PDO $pdo) {}

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM discussions WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function threadsForCourse(int $courseId, ?string $search = null): array
    {
        $sql = 'SELECT d.*, u.full_name AS author_name,
                       (SELECT COUNT(*) FROM discussions r WHERE r.parent_id = d.id) AS reply_count
                FROM discussions d
                JOIN users u ON u.id = d.author_id
                WHERE d.course_id = ?';
        $params = [$courseId];
        if ($search) {
            $sql .= ' AND d.body LIKE ?';
            $params[] = '%' . $search . '%';
        }
        $sql .= ' ORDER BY d.created_at DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function replies(int $parentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT d.*, u.full_name AS author_name
             FROM discussions d
             JOIN users u ON u.id = d.author_id
             WHERE d.parent_id = ?
             ORDER BY d.created_at ASC'
        );
        $stmt->execute([$parentId]);
        return $stmt->fetchAll();
    }

    public function create(int $courseId, ?int $parentId, int $authorId, string $body): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO discussions (course_id, parent_id, author_id, body) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$courseId, $parentId, $authorId, $body]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, string $body): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE discussions SET body = ?, updated_at = datetime(\'now\') WHERE id = ?'
        );
        return $stmt->execute([$body, $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM discussions WHERE id = ?');
        return $stmt->execute([$id]);
    }
}
