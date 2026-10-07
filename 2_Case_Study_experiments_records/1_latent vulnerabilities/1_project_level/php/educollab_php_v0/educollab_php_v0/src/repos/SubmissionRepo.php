<?php

class SubmissionRepo {
    public function __construct(private PDO $pdo) {}

    public function create(int $courseId, int $assignmentId, int $studentId, string $contentText, ?int $attachmentUploadId): array {
        $now = now_iso();
        $stmt = $this->pdo->prepare('INSERT INTO submissions(assignment_id,course_id,student_id,content_text,attachment_upload_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?)');
        $stmt->execute([$assignmentId, $courseId, $studentId, $contentText, $attachmentUploadId, $now, $now]);
        return $this->getById($courseId, $assignmentId, (int)$this->pdo->lastInsertId());
    }

    public function update(int $courseId, int $assignmentId, int $submissionId, int $studentId, string $contentText, ?int $attachmentUploadId): ?array {
        $stmt = $this->pdo->prepare('UPDATE submissions SET content_text=?, attachment_upload_id=?, updated_at=? WHERE course_id=? AND assignment_id=? AND submission_id=? AND student_id=?');
        $stmt->execute([$contentText, $attachmentUploadId, now_iso(), $courseId, $assignmentId, $submissionId, $studentId]);
        return $this->getById($courseId, $assignmentId, $submissionId);
    }

    public function getById(int $courseId, int $assignmentId, int $submissionId): ?array {
        $stmt = $this->pdo->prepare('SELECT s.*, u.username AS student_username FROM submissions s JOIN users u ON u.user_id=s.student_id WHERE s.course_id=? AND s.assignment_id=? AND s.submission_id=?');
        $stmt->execute([$courseId, $assignmentId, $submissionId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getMine(int $courseId, int $assignmentId, int $studentId): ?array {
        $stmt = $this->pdo->prepare('SELECT s.*, u.username AS student_username FROM submissions s JOIN users u ON u.user_id=s.student_id WHERE s.course_id=? AND s.assignment_id=? AND s.student_id=?');
        $stmt->execute([$courseId, $assignmentId, $studentId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function listMine(int $studentId): array {
        $stmt = $this->pdo->prepare('SELECT s.*, a.title AS assignment_title, c.title AS course_title FROM submissions s JOIN assignments a ON a.assignment_id=s.assignment_id JOIN courses c ON c.course_id=s.course_id WHERE s.student_id=? ORDER BY s.updated_at DESC');
        $stmt->execute([$studentId]);
        return $stmt->fetchAll();
    }

    public function listForAssignment(int $courseId, int $assignmentId): array {
        $stmt = $this->pdo->prepare('SELECT s.*, u.username AS student_username FROM submissions s JOIN users u ON u.user_id=s.student_id WHERE s.course_id=? AND s.assignment_id=? ORDER BY s.updated_at DESC');
        $stmt->execute([$courseId, $assignmentId]);
        return $stmt->fetchAll();
    }
}

