<?php
declare(strict_types=1);

namespace LMS\Repository;

use PDO;

final class CourseRepository
{
    public function __construct(private PDO $pdo) {}

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.*, u.full_name AS instructor_name
             FROM courses c
             JOIN users u ON u.id = c.instructor_id
             WHERE c.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByCode(string $code): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM courses WHERE code = ?');
        $stmt->execute([$code]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Discovery query — returns courses visible to the visitor.
     * Filters: title search, category, instructor, semester.
     */
    public function search(array $filters, bool $includeHidden = false): array
    {
        $sql = 'SELECT c.*, u.full_name AS instructor_name
                FROM courses c
                JOIN users u ON u.id = c.instructor_id
                WHERE 1 = 1';
        $params = [];

        if (!empty($filters['q'])) {
            $sql .= ' AND (c.title LIKE ? OR c.code LIKE ? OR c.description LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            $params[] = $like; $params[] = $like; $params[] = $like;
        }
        if (!empty($filters['category'])) {
            $sql .= ' AND c.category = ?';
            $params[] = $filters['category'];
        }
        if (!empty($filters['instructor'])) {
            $sql .= ' AND u.full_name = ?';
            $params[] = $filters['instructor'];
        }
        if (!empty($filters['semester'])) {
            $sql .= ' AND c.semester = ?';
            $params[] = $filters['semester'];
        }
        if (!$includeHidden) {
            $sql .= " AND c.visibility <> 'hidden'";
        }
        $sql .= ' ORDER BY c.code';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function listCategories(): array
    {
        return $this->pdo->query('SELECT DISTINCT category FROM courses ORDER BY category')->fetchAll(PDO::FETCH_COLUMN);
    }

    public function listSemesters(): array
    {
        return $this->pdo->query('SELECT DISTINCT semester FROM courses ORDER BY semester DESC')->fetchAll(PDO::FETCH_COLUMN);
    }

    public function listInstructors(): array
    {
        return $this->pdo
            ->query("SELECT DISTINCT u.full_name FROM users u JOIN courses c ON c.instructor_id = u.id ORDER BY u.full_name")
            ->fetchAll(PDO::FETCH_COLUMN);
    }

    public function listForInstructor(int $instructorId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM courses WHERE instructor_id = ? ORDER BY code');
        $stmt->execute([$instructorId]);
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO courses (code, title, description, category, semester, instructor_id, visibility)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['code'],
            $data['title'],
            $data['description'] ?? '',
            $data['category'] ?? 'General',
            $data['semester'] ?? ($_ENV['DEFAULT_SEMESTER'] ?? '2026-Fall'),
            $data['instructor_id'],
            $data['visibility'] ?? 'open',
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE courses SET title = ?, description = ?, category = ?, semester = ?, visibility = ?
             WHERE id = ?'
        );
        return $stmt->execute([
            $data['title'],
            $data['description'] ?? '',
            $data['category'] ?? 'General',
            $data['semester'] ?? '2026-Fall',
            $data['visibility'] ?? 'open',
            $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM courses WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function countEnrolled(int $courseId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE course_id = ? AND status = 'active'");
        $stmt->execute([$courseId]);
        return (int)$stmt->fetchColumn();
    }
}
