<?php

declare(strict_types=1);

final class CourseRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO courses (title, description, created_by, created_at)
             VALUES (:title, :description, :created_by, :created_at)'
        );
        $stmt->execute([
            ':title' => $data['title'],
            ':description' => $data['description'],
            ':created_by' => $data['created_by'],
            ':created_at' => date('c'),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function all(): array
    {
        $stmt = $this->pdo->query(
            'SELECT c.course_id, c.title, c.description, c.created_by, c.created_at, u.username AS creator_username, u.display_name AS creator_display_name
             FROM courses c
             JOIN users u ON u.user_id = c.created_by
             ORDER BY c.course_id DESC'
        );
        return $stmt->fetchAll();
    }

    public function findById(int $courseId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.course_id, c.title, c.description, c.created_by, c.created_at, u.username AS creator_username, u.display_name AS creator_display_name
             FROM courses c
             JOIN users u ON u.user_id = c.created_by
             WHERE c.course_id = :course_id
             LIMIT 1'
        );
        $stmt->execute([':course_id' => $courseId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function update(int $courseId, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE courses SET title = :title, description = :description WHERE course_id = :course_id'
        );
        return $stmt->execute([
            ':title' => $data['title'],
            ':description' => $data['description'],
            ':course_id' => $courseId,
        ]);
    }

    public function delete(int $courseId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM courses WHERE course_id = :course_id');
        return $stmt->execute([':course_id' => $courseId]);
    }
}
