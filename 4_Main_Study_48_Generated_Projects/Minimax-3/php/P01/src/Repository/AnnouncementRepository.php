<?php
declare(strict_types=1);

namespace LMS\Repository;

use PDO;

final class AnnouncementRepository
{
    public function __construct(private PDO $pdo) {}

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM announcements WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function listForCourse(int $courseId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.*, u.full_name AS poster_name
             FROM announcements a
             JOIN users u ON u.id = a.posted_by
             WHERE a.course_id = ?
             ORDER BY a.created_at DESC'
        );
        $stmt->execute([$courseId]);
        return $stmt->fetchAll();
    }

    public function create(int $courseId, int $postedBy, string $title, string $body): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO announcements (course_id, title, body, posted_by) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$courseId, $title, $body, $postedBy]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, string $title, string $body): bool
    {
        $stmt = $this->pdo->prepare('UPDATE announcements SET title = ?, body = ? WHERE id = ?');
        return $stmt->execute([$title, $body, $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM announcements WHERE id = ?');
        return $stmt->execute([$id]);
    }
}
