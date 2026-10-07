<?php

class CourseRepo {
    public function __construct(private PDO $pdo) {}

    public function create(int $createdBy, string $title, string $description): array {
        $stmt = $this->pdo->prepare('INSERT INTO courses(title,description,created_by,created_at) VALUES (?,?,?,?)');
        $stmt->execute([$title, $description, $createdBy, now_iso()]);
        return $this->getById((int)$this->pdo->lastInsertId());
    }

    public function listAll(): array {
        $stmt = $this->pdo->query('SELECT c.*, u.username AS creator_username FROM courses c JOIN users u ON u.user_id=c.created_by ORDER BY c.course_id DESC');
        return $stmt->fetchAll();
    }

    public function getById(int $courseId): ?array {
        $stmt = $this->pdo->prepare('SELECT c.*, u.username AS creator_username FROM courses c JOIN users u ON u.user_id=c.created_by WHERE c.course_id=?');
        $stmt->execute([$courseId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function update(int $courseId, string $title, string $description): ?array {
        $stmt = $this->pdo->prepare('UPDATE courses SET title=?, description=? WHERE course_id=?');
        $stmt->execute([$title, $description, $courseId]);
        return $this->getById($courseId);
    }

    public function delete(int $courseId): void {
        $stmt = $this->pdo->prepare('DELETE FROM courses WHERE course_id=?');
        $stmt->execute([$courseId]);
    }
}

