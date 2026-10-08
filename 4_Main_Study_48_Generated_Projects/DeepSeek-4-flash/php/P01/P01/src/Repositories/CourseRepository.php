<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database;

/**
 * Courses, enrollments, materials, announcements and discussion threads.
 */
final class CourseRepository
{
    public function __construct(private Database $db)
    {
    }

    /**
     * Search courses. Private courses only appear for their owner or admins.
     *
     * @return list<array<string, mixed>>
     */
    public function discover(array $filters, bool $includePrivate): array
    {
        $sql = 'SELECT c.*, u.display_name AS instructor_name, u.username AS instructor_username,
                       (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND e.status = "enrolled") AS enrollment_count
                FROM courses c
                LEFT JOIN users u ON u.id = c.instructor_id
                WHERE 1 = 1';
        $params = [];
        if (!$includePrivate) {
            $sql .= ' AND c.visibility = "public"';
        }
        if (($filters['status'] ?? '') !== '' && $filters['status'] !== 'all') {
            $sql .= ' AND c.status = ?';
            $params[] = $filters['status'];
        }
        if (($filters['visibility'] ?? '') !== '' && $filters['visibility'] !== 'all') {
            $sql .= ' AND c.visibility = ?';
            $params[] = $filters['visibility'];
        }
        if (($filters['category'] ?? '') !== '' && $filters['category'] !== 'all') {
            $sql .= ' AND c.category = ?';
            $params[] = $filters['category'];
        }
        if (($filters['semester'] ?? '') !== '' && $filters['semester'] !== 'all') {
            $sql .= ' AND c.semester = ?';
            $params[] = $filters['semester'];
        }
        if (($filters['instructor_id'] ?? '') !== '' && $filters['instructor_id'] !== 'all') {
            $sql .= ' AND c.instructor_id = ?';
            $params[] = (int) $filters['instructor_id'];
        }
        if (($filters['q'] ?? '') !== '') {
            $sql .= ' AND (c.title LIKE ? OR c.description LIKE ? OR c.category LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        $sql .= ' ORDER BY c.id ASC LIMIT 200';
        return $this->db->select($sql, $params);
    }

    public function find(int $id): ?array
    {
        return $this->db->first('SELECT c.*, u.display_name AS instructor_name, u.username AS instructor_username FROM courses c LEFT JOIN users u ON u.id = c.instructor_id WHERE c.id = ?', [$id]);
    }

    public function create(array $data): int
    {
        return $this->db->insert('courses', $data);
    }

    public function update(int $id, array $data): void
    {
        $this->db->update('courses', $data, 'id = :id', ['id' => $id]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function categories(): array
    {
        return array_map(static fn ($r) => $r['category'], $this->db->select('SELECT DISTINCT category FROM courses ORDER BY category'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function semesters(): array
    {
        return array_map(static fn ($r) => $r['semester'], $this->db->select('SELECT DISTINCT semester FROM courses ORDER BY semester'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function instructors(): array
    {
        return $this->db->select('SELECT u.id, u.display_name FROM users u JOIN roles r ON r.id = u.role_id WHERE r.name = "instructor" ORDER BY u.display_name');
    }

    public function isEnrolled(int $courseId, int $userId, string $status = 'enrolled'): bool
    {
        return $this->db->first('SELECT id FROM enrollments WHERE course_id = ? AND user_id = ? AND status = ?', [$courseId, $userId, $status]) !== null;
    }

    public function enroll(int $courseId, int $userId, string $status = 'enrolled'): int
    {
        $existing = $this->db->first('SELECT id, status FROM enrollments WHERE course_id = ? AND user_id = ?', [$courseId, $userId]);
        if ($existing !== null) {
            if ($existing['status'] !== $status) {
                $this->db->update('enrollments', ['status' => $status, 'enrolled_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => (int) $existing['id']]);
            }
            return (int) $existing['id'];
        }
        return $this->db->insert('enrollments', ['course_id' => $courseId, 'user_id' => $userId, 'status' => $status]);
    }

    public function setEnrollmentStatus(int $enrollmentId, string $status): void
    {
        $this->db->update('enrollments', ['status' => $status], 'id = :id', ['id' => $enrollmentId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function roster(int $courseId): array
    {
        return $this->db->select(
            'SELECT e.id, e.status, e.enrolled_at, u.username, u.display_name, u.email
             FROM enrollments e JOIN users u ON u.id = e.user_id
             WHERE e.course_id = ? ORDER BY e.status, u.display_name',
            [$courseId]
        );
    }

    /**
     * Enrollments for a student.
     *
     * @return list<array<string, mixed>>
     */
    public function enrollmentsForUser(int $userId, string $status = 'enrolled'): array
    {
        return $this->db->select(
            'SELECT e.*, c.title AS course_title, c.category, c.semester, u.display_name AS instructor_name
             FROM enrollments e
             JOIN courses c ON c.id = e.course_id
             LEFT JOIN users u ON u.id = c.instructor_id
             WHERE e.user_id = ? AND e.status = ? ORDER BY c.title',
            [$userId, $status]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function allEnrollments(string $status = ''): array
    {
        $sql = 'SELECT e.*, c.title AS course_title, u.username, u.display_name FROM enrollments e
                JOIN courses c ON c.id = e.course_id JOIN users u ON u.id = e.user_id WHERE 1 = 1';
        $params = [];
        if ($status !== '' && $status !== 'all') {
            $sql .= ' AND e.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY e.id DESC LIMIT 300';
        return $this->db->select($sql, $params);
    }

    // ---- Materials ----

    /**
     * @return list<array<string, mixed>>
     */
    public function materialsForCourse(int $courseId): array
    {
        return $this->db->select(
            'SELECT m.*, u.display_name AS uploader_name FROM materials m JOIN users u ON u.id = m.uploader_id
             WHERE m.course_id = ? ORDER BY m.created_at DESC',
            [$courseId]
        );
    }

    public function material(int $id): ?array
    {
        return $this->db->first('SELECT * FROM materials WHERE id = ?', [$id]);
    }

    public function addMaterial(array $data): int
    {
        return $this->db->insert('materials', $data);
    }

    public function updateMaterial(int $id, array $data): void
    {
        $this->db->update('materials', $data, 'id = :id', ['id' => $id]);
    }

    // ---- Announcements ----

    /**
     * @return list<array<string, mixed>>
     */
    public function announcementsForCourse(int $courseId): array
    {
        return $this->db->select(
            'SELECT a.*, u.display_name AS author_name FROM announcements a JOIN users u ON u.id = a.instructor_id
             WHERE a.course_id = ? ORDER BY a.published_at DESC',
            [$courseId]
        );
    }

    /**
     * Announcements across the courses a user is enrolled in / owns.
     *
     * @return list<array<string, mixed>>
     */
    public function announcementsForUser(int $userId): array
    {
        return $this->db->select(
            'SELECT a.*, u.display_name AS author_name, c.title AS course_title
             FROM announcements a
             JOIN users u ON u.id = a.instructor_id
             JOIN courses c ON c.id = a.course_id
             JOIN enrollments e ON e.course_id = c.id AND e.user_id = ? AND e.status = "enrolled"
             ORDER BY a.published_at DESC LIMIT 50',
            [$userId]
        );
    }

    public function announcement(int $id): ?array
    {
        return $this->db->first('SELECT * FROM announcements WHERE id = ?', [$id]);
    }

    public function addAnnouncement(array $data): int
    {
        return $this->db->insert('announcements', $data);
    }

    public function updateAnnouncement(int $id, array $data): void
    {
        $this->db->update('announcements', $data, 'id = :id', ['id' => $id]);
    }

    // ---- Discussions ----

    /**
     * @return list<array<string, mixed>>
     */
    public function threadsForCourse(int $courseId, string $q = ''): array
    {
        $sql = 'SELECT d.*, u.display_name AS author_name, u.username AS author_username,
                       (SELECT COUNT(*) FROM discussions r WHERE r.parent_id = d.id) AS reply_count
                FROM discussions d
                JOIN users u ON u.id = d.author_id
                WHERE d.course_id = ? AND d.parent_id IS NULL';
        $params = [$courseId];
        if ($q !== '') {
            $sql .= ' AND (d.subject LIKE ? OR d.body LIKE ?)';
            $like = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
        }
        $sql .= ' ORDER BY d.created_at DESC LIMIT 200';
        return $this->db->select($sql, $params);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function repliesForThread(int $threadId): array
    {
        return $this->db->select(
            'SELECT d.*, u.display_name AS author_name, u.username AS author_username
             FROM discussions d JOIN users u ON u.id = d.author_id
             WHERE d.parent_id = ? ORDER BY d.created_at ASC',
            [$threadId]
        );
    }

    public function discussion(int $id): ?array
    {
        return $this->db->first('SELECT d.*, u.display_name AS author_name, u.username AS author_username FROM discussions d JOIN users u ON u.id = d.author_id WHERE d.id = ?', [$id]);
    }

    public function addDiscussion(array $data): int
    {
        return $this->db->insert('discussions', $data);
    }

    public function updateDiscussion(int $id, array $data): void
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db->update('discussions', $data, 'id = :id', ['id' => $id]);
    }
}
