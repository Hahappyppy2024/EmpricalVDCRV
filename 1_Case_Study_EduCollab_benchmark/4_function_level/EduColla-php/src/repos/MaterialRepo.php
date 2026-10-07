<?php
class MaterialRepo {
    private PDO $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function create(int $courseId, int $uploadedBy, string $originalName, string $zipPath, string $extractDir): int {
        $stmt = $this->pdo->prepare("INSERT INTO materials (course_id, uploaded_by, original_name, zip_path, extract_dir, created_at) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$courseId, $uploadedBy, $originalName, $zipPath, $extractDir, gmdate('c')]);
        return (int)$this->pdo->lastInsertId();
    }

    public function listByCourse(int $courseId): array {
        $stmt = $this->pdo->prepare("SELECT * FROM materials WHERE course_id = ? ORDER BY material_id DESC");
        $stmt->execute([$courseId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function get(int $materialId): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM materials WHERE material_id = ?");
        $stmt->execute([$materialId]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    public function delete(int $materialId): void {
        $stmt = $this->pdo->prepare("DELETE FROM materials WHERE material_id = ?");
        $stmt->execute([$materialId]);
    }
}
