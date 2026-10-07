<?php

class UploadRepo {
    public function __construct(private PDO $pdo) {}

    public function create(int $courseId, int $uploadedBy, string $originalName, string $storagePath): array {
        $stmt = $this->pdo->prepare('INSERT INTO uploads(course_id,uploaded_by,original_name,storage_path,created_at) VALUES (?,?,?,?,?)');
        $stmt->execute([$courseId, $uploadedBy, $originalName, $storagePath, now_iso()]);
        return $this->getById($courseId, (int)$this->pdo->lastInsertId());
    }

    public function listByCourse(int $courseId): array {
        $stmt = $this->pdo->prepare('SELECT up.*, u.username AS uploaded_by_username FROM uploads up JOIN users u ON u.user_id=up.uploaded_by WHERE up.course_id=? ORDER BY up.upload_id DESC');
        $stmt->execute([$courseId]);
        return $stmt->fetchAll();
    }

    public function getById(int $courseId, int $uploadId): ?array {
        $stmt = $this->pdo->prepare('SELECT up.*, u.username AS uploaded_by_username FROM uploads up JOIN users u ON u.user_id=up.uploaded_by WHERE up.course_id=? AND up.upload_id=?');
        $stmt->execute([$courseId, $uploadId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function delete(int $courseId, int $uploadId): void {
        $stmt = $this->pdo->prepare('DELETE FROM uploads WHERE course_id=? AND upload_id=?');
        $stmt->execute([$courseId, $uploadId]);
    }
}

