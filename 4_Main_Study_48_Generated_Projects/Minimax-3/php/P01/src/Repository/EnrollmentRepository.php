<?php
declare(strict_types=1);

namespace LMS\Repository;

use PDO;

final class EnrollmentRepository
{
    public function __construct(private PDO $pdo) {}

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM enrollments WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function isEnrolled(int $userId, int $courseId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM enrollments WHERE user_id = ? AND course_id = ? AND status = 'active'"
        );
        $stmt->execute([$userId, $courseId]);
        return (bool)$stmt->fetchColumn();
    }

    public function enroll(int $userId, int $courseId, string $role = 'student'): array
    {
        if ($this->isEnrolled($userId, $courseId)) {
            return ['ok' => false, 'error' => 'Already enrolled.'];
        }
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO enrollments (user_id, course_id, role, status) VALUES (?, ?, ?, 'active')"
            );
            $stmt->execute([$userId, $courseId, $role]);
        } catch (\PDOException $e) {
            return ['ok' => false, 'error' => 'Could not enroll.'];
        }
        return ['ok' => true, 'id' => (int)$this->pdo->lastInsertId()];
    }

    public function drop(int $userId, int $courseId): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE enrollments SET status = 'dropped' WHERE user_id = ? AND course_id = ?"
        );
        return $stmt->execute([$userId, $courseId]);
    }

    public function setStatus(int $enrollmentId, string $status): bool
    {
        $stmt = $this->pdo->prepare('UPDATE enrollments SET status = ? WHERE id = ?');
        return $stmt->execute([$status, $enrollmentId]);
    }

    public function forUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT e.*, c.code, c.title, c.semester, c.category, u.full_name AS instructor_name
             FROM enrollments e
             JOIN courses c ON c.id = e.course_id
             JOIN users u ON u.id = c.instructor_id
             WHERE e.user_id = ? AND e.status = 'active'
             ORDER BY c.code"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function roster(int $courseId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT e.id, e.user_id, e.role, e.status, e.created_at,
                    u.username, u.email, u.full_name
             FROM enrollments e
             JOIN users u ON u.id = e.user_id
             WHERE e.course_id = ?
             ORDER BY u.full_name"
        );
        $stmt->execute([$courseId]);
        return $stmt->fetchAll();
    }
}
