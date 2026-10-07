<?php
class GradeRepo {
    private PDO $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function upsertGrade(int $courseId, int $assignmentId, int $studentId, $score, string $feedback = null): void {
        $now = gmdate('c');
        $stmt = $this->pdo->prepare("INSERT INTO assignment_grades(course_id, assignment_id, student_id, score, feedback, created_at, updated_at)
            VALUES(?,?,?,?,?,?,?)
            ON CONFLICT(assignment_id, student_id) DO UPDATE SET score=excluded.score, feedback=excluded.feedback, updated_at=excluded.updated_at");
        $stmt->execute([$courseId, $assignmentId, $studentId, $score, $feedback, $now, $now]);
    }

    public function listGradesForAssignment(int $courseId, int $assignmentId): array {
        $stmt = $this->pdo->prepare("SELECT g.*, u.username AS student_username
            FROM assignment_grades g JOIN users u ON u.user_id=g.student_id
            WHERE g.course_id=? AND g.assignment_id=?
            ORDER BY u.username ASC");
        $stmt->execute([$courseId, $assignmentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getGrade(int $courseId, int $assignmentId, int $studentId): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM assignment_grades WHERE course_id=? AND assignment_id=? AND student_id=?");
        $stmt->execute([$courseId, $assignmentId, $studentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
