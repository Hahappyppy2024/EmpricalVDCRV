<?php
declare(strict_types=1);

namespace LMS\Repository;

use PDO;

final class AssignmentRepository
{
    public function __construct(private PDO $pdo) {}

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM assignments WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function listForCourse(int $courseId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.*,
                    (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id) AS submission_count
             FROM assignments a WHERE a.course_id = ? ORDER BY a.due_at DESC'
        );
        $stmt->execute([$courseId]);
        return $stmt->fetchAll();
    }

    public function listForCourseWithSubmission(int $courseId, int $studentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.*,
                    s.id AS submission_id, s.status AS submission_status, s.score AS submission_score,
                    s.submitted_at AS submission_submitted_at
             FROM assignments a
             LEFT JOIN submissions s ON s.assignment_id = a.id AND s.student_id = ?
             WHERE a.course_id = ?
             ORDER BY a.due_at DESC'
        );
        $stmt->execute([$studentId, $courseId]);
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO assignments (course_id, title, instructions, due_at, max_score, created_by)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['course_id'],
            $data['title'],
            $data['instructions'] ?? '',
            $data['due_at'],
            (float)($data['max_score'] ?? 100),
            $data['created_by'],
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE assignments SET title = ?, instructions = ?, due_at = ?, max_score = ? WHERE id = ?'
        );
        return $stmt->execute([
            $data['title'],
            $data['instructions'] ?? '',
            $data['due_at'],
            (float)($data['max_score'] ?? 100),
            $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM assignments WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function submit(int $assignmentId, int $studentId, array $file, string $comment): array
    {
        $assignment = $this->find($assignmentId);
        if (!$assignment) {
            return ['ok' => false, 'error' => 'Assignment not found.'];
        }
        $existing = $this->findSubmission($assignmentId, $studentId);
        if ($existing) {
            return ['ok' => false, 'error' => 'You have already submitted this assignment.'];
        }
        $isLate = strtotime($assignment['due_at']) < time();
        $stmt = $this->pdo->prepare(
            'INSERT INTO submissions
               (assignment_id, student_id, filename, original_name, mime, size, comment, status, submitted_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, datetime(\'now\'))'
        );
        $stmt->execute([
            $assignmentId,
            $studentId,
            $file['stored_name'],
            $file['original_name'],
            $file['mime'],
            $file['size'],
            $comment,
            $isLate ? 'late' : 'submitted',
        ]);
        return ['ok' => true, 'id' => (int)$this->pdo->lastInsertId(), 'late' => $isLate];
    }

    public function findSubmission(int $assignmentId, int $studentId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM submissions WHERE assignment_id = ? AND student_id = ?'
        );
        $stmt->execute([$assignmentId, $studentId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function listSubmissions(int $assignmentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.*, u.full_name AS student_name, u.username
             FROM submissions s JOIN users u ON u.id = s.student_id
             WHERE s.assignment_id = ?
             ORDER BY s.submitted_at DESC'
        );
        $stmt->execute([$assignmentId]);
        return $stmt->fetchAll();
    }

    public function grade(int $submissionId, float $score, ?string $feedback, int $graderId): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE submissions SET score = ?, feedback = ?, graded_by = ?, graded_at = datetime('now'), status = 'graded'
             WHERE id = ?"
        );
        return $stmt->execute([$score, $feedback, $graderId, $submissionId]);
    }

    public function findSubmissionById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.*, a.course_id, a.title AS assignment_title, u.full_name AS student_name
             FROM submissions s
             JOIN assignments a ON a.id = s.assignment_id
             JOIN users u ON u.id = s.student_id
             WHERE s.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
