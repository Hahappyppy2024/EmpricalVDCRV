<?php
declare(strict_types=1);

namespace LMS\Repository;

use PDO;

final class MaterialRepository
{
    public function __construct(private PDO $pdo) {}

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM materials WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function listForCourse(int $courseId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT m.*, u.full_name AS uploader_name
             FROM materials m
             JOIN users u ON u.id = m.uploaded_by
             WHERE m.course_id = ?
             ORDER BY m.created_at DESC'
        );
        $stmt->execute([$courseId]);
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO materials (course_id, title, description, filename, original_name, mime, size, uploaded_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['course_id'],
            $data['title'],
            $data['description'],
            $data['filename'],
            $data['original_name'],
            $data['mime'],
            $data['size'],
            $data['uploaded_by'],
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM materials WHERE id = ?');
        return $stmt->execute([$id]);
    }
}
