<?php
declare(strict_types=1);

namespace App;

use PDO;

final class Access
{
    public function __construct(private PDO $db) {}

    public function course(int $courseId): array
    {
        $stmt = $this->db->prepare('SELECT c.*,cat.name category_name FROM courses c JOIN categories cat ON cat.id=c.category_id WHERE c.id=?');
        $stmt->execute([$courseId]);
        $course = $stmt->fetch();
        if (!$course) throw new ApiException(404, 'course_not_found', 'Course not found.');
        return $course;
    }

    public function isInstructor(int $courseId, int $userId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM course_instructors WHERE course_id=? AND user_id=?');
        $stmt->execute([$courseId, $userId]);
        return (bool)$stmt->fetchColumn();
    }

    public function isEnrolled(int $courseId, int $userId): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM enrollments WHERE course_id=? AND student_id=? AND status IN ('active','completed')");
        $stmt->execute([$courseId, $userId]);
        return (bool)$stmt->fetchColumn();
    }

    public function requireInstructor(array $user, int $courseId): void
    {
        if ($user['role'] !== 'admin' && !$this->isInstructor($courseId, (int)$user['id'])) throw new ApiException(403, 'course_access_denied', 'Course instructor access is required.');
    }

    public function requireMember(array $user, int $courseId): void
    {
        if ($user['role'] === 'admin' || $this->isInstructor($courseId, (int)$user['id']) || $this->isEnrolled($courseId, (int)$user['id'])) return;
        throw new ApiException(403, 'course_access_denied', 'Course membership is required.');
    }

    public function writableCourse(int $courseId): array
    {
        $course = $this->course($courseId);
        if ($course['status'] === 'archived') throw new ApiException(409, 'course_archived', 'Archived courses are read-only.');
        return $course;
    }
}
