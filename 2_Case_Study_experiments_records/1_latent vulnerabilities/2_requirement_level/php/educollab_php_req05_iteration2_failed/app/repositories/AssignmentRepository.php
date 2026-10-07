<?php

declare(strict_types=1);

final class AssignmentRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(array $data): int
    {
        $now = date('c');
        $stmt = $this->pdo->prepare(
            'INSERT INTO assignments (course_id, created_by, title, description, due_at, created_at, updated_at)
             VALUES (:course_id, :created_by, :title, :description, :due_at, :created_at, :updated_at)'
        );
        $stmt->execute([
            ':course_id' => $data['course_id'],
            ':created_by' => $data['created_by'],
            ':title' => $data['title'],
            ':description' => $data['description'],
            ':due_at' => $data['due_at'],
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function listByCourse(int $courseId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.*, u.username AS creator_username, u.display_name AS creator_display_name
             FROM assignments a
             JOIN users u ON u.user_id = a.created_by
             WHERE a.course_id = :course_id
             ORDER BY a.assignment_id DESC'
        );
        $stmt->execute([':course_id' => $courseId]);
        return $stmt->fetchAll();
    }

    public function findById(int $courseId, int $assignmentId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.*, u.username AS creator_username, u.display_name AS creator_display_name
             FROM assignments a
             JOIN users u ON u.user_id = a.created_by
             WHERE a.course_id = :course_id AND a.assignment_id = :assignment_id
             LIMIT 1'
        );
        $stmt->execute([':course_id' => $courseId, ':assignment_id' => $assignmentId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function update(int $courseId, int $assignmentId, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE assignments
             SET title = :title, description = :description, due_at = :due_at, updated_at = :updated_at
             WHERE course_id = :course_id AND assignment_id = :assignment_id'
        );
        return $stmt->execute([
            ':title' => $data['title'],
            ':description' => $data['description'],
            ':due_at' => $data['due_at'],
            ':updated_at' => date('c'),
            ':course_id' => $courseId,
            ':assignment_id' => $assignmentId,
        ]);
    }

    public function delete(int $courseId, int $assignmentId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM assignments WHERE course_id = :course_id AND assignment_id = :assignment_id');
        return $stmt->execute([':course_id' => $courseId, ':assignment_id' => $assignmentId]);
    }
}
