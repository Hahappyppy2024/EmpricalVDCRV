<?php

declare(strict_types=1);

final class UploadRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO uploads (course_id, uploaded_by, original_name, storage_path, created_at)
             VALUES (:course_id, :uploaded_by, :original_name, :storage_path, :created_at)'
        );
        $stmt->execute([
            ':course_id' => $data['course_id'],
            ':uploaded_by' => $data['uploaded_by'],
            ':original_name' => $data['original_name'],
            ':storage_path' => $data['storage_path'],
            ':created_at' => date('c'),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function listByCourse(int $courseId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT up.*, u.username AS uploader_username, u.display_name AS uploader_display_name
             FROM uploads up
             JOIN users u ON u.user_id = up.uploaded_by
             WHERE up.course_id = :course_id
             ORDER BY up.upload_id DESC'
        );
        $stmt->execute([':course_id' => $courseId]);
        return $stmt->fetchAll();
    }

    public function listByCourseAndUploader(int $courseId, int $uploadedBy): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT up.*, u.username AS uploader_username, u.display_name AS uploader_display_name
             FROM uploads up
             JOIN users u ON u.user_id = up.uploaded_by
             WHERE up.course_id = :course_id AND up.uploaded_by = :uploaded_by
             ORDER BY up.upload_id DESC'
        );
        $stmt->execute([':course_id' => $courseId, ':uploaded_by' => $uploadedBy]);
        return $stmt->fetchAll();
    }

    public function findById(int $courseId, int $uploadId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT up.*, u.username AS uploader_username, u.display_name AS uploader_display_name
             FROM uploads up
             JOIN users u ON u.user_id = up.uploaded_by
             WHERE up.course_id = :course_id AND up.upload_id = :upload_id
             LIMIT 1'
        );
        $stmt->execute([':course_id' => $courseId, ':upload_id' => $uploadId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByIdAndUploader(int $courseId, int $uploadId, int $uploadedBy): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT up.*, u.username AS uploader_username, u.display_name AS uploader_display_name
             FROM uploads up
             JOIN users u ON u.user_id = up.uploaded_by
             WHERE up.course_id = :course_id AND up.upload_id = :upload_id AND up.uploaded_by = :uploaded_by
             LIMIT 1'
        );
        $stmt->execute([
            ':course_id' => $courseId,
            ':upload_id' => $uploadId,
            ':uploaded_by' => $uploadedBy,
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function delete(int $courseId, int $uploadId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM uploads WHERE course_id = :course_id AND upload_id = :upload_id');
        return $stmt->execute([':course_id' => $courseId, ':upload_id' => $uploadId]);
    }
}