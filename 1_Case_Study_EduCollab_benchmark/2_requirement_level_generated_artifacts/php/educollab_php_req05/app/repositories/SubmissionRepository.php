<?php

declare(strict_types=1);

final class SubmissionRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(array $data): int
    {
        $now = date('c');
        $stmt = $this->pdo->prepare(
            'INSERT INTO submissions (assignment_id, course_id, student_id, content_text, attachment_upload_id, created_at, updated_at)
             VALUES (:assignment_id, :course_id, :student_id, :content_text, :attachment_upload_id, :created_at, :updated_at)'
        );
        $stmt->execute([
            ':assignment_id' => $data['assignment_id'],
            ':course_id' => $data['course_id'],
            ':student_id' => $data['student_id'],
            ':content_text' => $data['content_text'],
            ':attachment_upload_id' => $data['attachment_upload_id'],
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function findById(int $courseId, int $assignmentId, int $submissionId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.*, u.username AS student_username, u.display_name AS student_display_name,
                    up.original_name AS attachment_original_name, up.storage_path AS attachment_storage_path
             FROM submissions s
             JOIN users u ON u.user_id = s.student_id
             LEFT JOIN uploads up ON up.upload_id = s.attachment_upload_id
             WHERE s.course_id = :course_id AND s.assignment_id = :assignment_id AND s.submission_id = :submission_id
             LIMIT 1'
        );
        $stmt->execute([
            ':course_id' => $courseId,
            ':assignment_id' => $assignmentId,
            ':submission_id' => $submissionId,
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function listByUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.*, a.title AS assignment_title, c.title AS course_title,
                    up.original_name AS attachment_original_name
             FROM submissions s
             JOIN assignments a ON a.assignment_id = s.assignment_id
             JOIN courses c ON c.course_id = s.course_id
             LEFT JOIN uploads up ON up.upload_id = s.attachment_upload_id
             WHERE s.student_id = :student_id
             ORDER BY s.submission_id DESC'
        );
        $stmt->execute([':student_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function listByAssignment(int $courseId, int $assignmentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.*, u.username AS student_username, u.display_name AS student_display_name,
                    up.original_name AS attachment_original_name
             FROM submissions s
             JOIN users u ON u.user_id = s.student_id
             LEFT JOIN uploads up ON up.upload_id = s.attachment_upload_id
             WHERE s.course_id = :course_id AND s.assignment_id = :assignment_id
             ORDER BY s.submission_id DESC'
        );
        $stmt->execute([':course_id' => $courseId, ':assignment_id' => $assignmentId]);
        return $stmt->fetchAll();
    }

    public function update(int $courseId, int $assignmentId, int $submissionId, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE submissions
             SET content_text = :content_text, attachment_upload_id = :attachment_upload_id, updated_at = :updated_at
             WHERE course_id = :course_id AND assignment_id = :assignment_id AND submission_id = :submission_id'
        );
        return $stmt->execute([
            ':content_text' => $data['content_text'],
            ':attachment_upload_id' => $data['attachment_upload_id'],
            ':updated_at' => date('c'),
            ':course_id' => $courseId,
            ':assignment_id' => $assignmentId,
            ':submission_id' => $submissionId,
        ]);
    }
}
