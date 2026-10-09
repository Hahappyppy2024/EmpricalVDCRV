<?php
declare(strict_types=1);

namespace LMS\Repository;

use PDO;

final class GradeRepository
{
    public function __construct(private PDO $pdo) {}

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM grades WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function forStudent(int $studentId, ?int $courseId = null): array
    {
        $sql = 'SELECT g.*, c.code AS course_code, c.title AS course_title
                FROM grades g JOIN courses c ON c.id = g.course_id
                WHERE g.student_id = ?';
        $params = [$studentId];
        if ($courseId) {
            $sql .= ' AND g.course_id = ?';
            $params[] = $courseId;
        }
        $sql .= ' ORDER BY g.graded_at DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function forCourse(int $courseId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT g.*, u.full_name AS student_name, u.username
             FROM grades g JOIN users u ON u.id = g.student_id
             WHERE g.course_id = ? ORDER BY u.full_name, g.graded_at DESC'
        );
        $stmt->execute([$courseId]);
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO grades (course_id, student_id, item_type, item_id, item_label,
                score, max_score, feedback, graded_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['course_id'],
            $data['student_id'],
            $data['item_type'],
            $data['item_id'] ?? null,
            $data['item_label'],
            (float)$data['score'],
            (float)($data['max_score'] ?? 100),
            $data['feedback'] ?? null,
            $data['graded_by'],
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, float $score, ?string $feedback): bool
    {
        $stmt = $this->pdo->prepare('UPDATE grades SET score = ?, feedback = ?, graded_at = datetime(\'now\') WHERE id = ?');
        return $stmt->execute([$score, $feedback, $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM grades WHERE id = ?');
        return $stmt->execute([$id]);
    }
}
