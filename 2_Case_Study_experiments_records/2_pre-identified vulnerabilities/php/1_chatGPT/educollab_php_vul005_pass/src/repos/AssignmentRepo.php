<?php

class AssignmentRepo {
    public function __construct(private PDO $pdo) {}

    public function create(int $courseId, int $createdBy, string $title, string $description, ?string $dueAt): array {
        $now = now_iso();
        $stmt = $this->pdo->prepare('INSERT INTO assignments(course_id,created_by,title,description,due_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?)');
        $stmt->execute([$courseId, $createdBy, $title, $description, $dueAt, $now, $now]);
        return $this->getById($courseId, (int)$this->pdo->lastInsertId());
    }

    public function listByCourse(int $courseId): array {
        $stmt = $this->pdo->prepare('SELECT a.*, u.username AS creator_username FROM assignments a JOIN users u ON u.user_id=a.created_by WHERE a.course_id=? ORDER BY a.assignment_id DESC');
        $stmt->execute([$courseId]);
        return $stmt->fetchAll();
    }

    public function getById(int $courseId, int $assignmentId): ?array {
        $stmt = $this->pdo->prepare('SELECT a.*, u.username AS creator_username FROM assignments a JOIN users u ON u.user_id=a.created_by WHERE a.course_id=? AND a.assignment_id=?');
        $stmt->execute([$courseId, $assignmentId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function update(int $courseId, int $assignmentId, string $title, string $description, ?string $dueAt): ?array {
        $stmt = $this->pdo->prepare('UPDATE assignments SET title=?, description=?, due_at=?, updated_at=? WHERE course_id=? AND assignment_id=?');
        $stmt->execute([$title, $description, $dueAt, now_iso(), $courseId, $assignmentId]);
        return $this->getById($courseId, $assignmentId);
    }

    public function delete(int $courseId, int $assignmentId): void {
        $stmt = $this->pdo->prepare('DELETE FROM assignments WHERE course_id=? AND assignment_id=?');
        $stmt->execute([$courseId, $assignmentId]);
    }
}

