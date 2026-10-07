<?php

declare(strict_types=1);

final class MembershipRepository
{
    private const ALLOWED_ROLES = ['admin', 'teacher', 'student', 'assistant', 'senior-assistant'];

    public function __construct(private PDO $pdo)
    {
    }

    public function allowedRoles(): array
    {
        return self::ALLOWED_ROLES;
    }

    public function isValidRole(string $role): bool
    {
        return in_array($role, self::ALLOWED_ROLES, true);
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO memberships (course_id, user_id, role_in_course, created_at)
             VALUES (:course_id, :user_id, :role_in_course, :created_at)'
        );
        $stmt->execute([
            ':course_id' => $data['course_id'],
            ':user_id' => $data['user_id'],
            ':role_in_course' => $data['role_in_course'],
            ':created_at' => date('c'),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function findById(int $membershipId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT m.membership_id, m.course_id, m.user_id, m.role_in_course, m.created_at,
                    u.username, u.display_name,
                    c.title AS course_title
             FROM memberships m
             JOIN users u ON u.user_id = m.user_id
             JOIN courses c ON c.course_id = m.course_id
             WHERE m.membership_id = :membership_id
             LIMIT 1'
        );
        $stmt->execute([':membership_id' => $membershipId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByCourseAndUser(int $courseId, int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT m.membership_id, m.course_id, m.user_id, m.role_in_course, m.created_at,
                    u.username, u.display_name
             FROM memberships m
             JOIN users u ON u.user_id = m.user_id
             WHERE m.course_id = :course_id AND m.user_id = :user_id
             LIMIT 1'
        );
        $stmt->execute([
            ':course_id' => $courseId,
            ':user_id' => $userId,
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function listByCourse(int $courseId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT m.membership_id, m.course_id, m.user_id, m.role_in_course, m.created_at,
                    u.username, u.display_name
             FROM memberships m
             JOIN users u ON u.user_id = m.user_id
             WHERE m.course_id = :course_id
             ORDER BY m.membership_id ASC'
        );
        $stmt->execute([':course_id' => $courseId]);
        return $stmt->fetchAll();
    }

    public function listByUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT m.membership_id, m.course_id, m.user_id, m.role_in_course, m.created_at,
                    c.title AS course_title
             FROM memberships m
             JOIN courses c ON c.course_id = m.course_id
             WHERE m.user_id = :user_id
             ORDER BY m.membership_id ASC'
        );
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function updateRole(int $membershipId, string $role): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE memberships SET role_in_course = :role_in_course WHERE membership_id = :membership_id'
        );
        return $stmt->execute([
            ':role_in_course' => $role,
            ':membership_id' => $membershipId,
        ]);
    }

    public function delete(int $membershipId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM memberships WHERE membership_id = :membership_id');
        return $stmt->execute([':membership_id' => $membershipId]);
    }

    public function ensureMembership(int $courseId, int $userId, string $role): int
    {
        $existing = $this->findByCourseAndUser($courseId, $userId);
        if ($existing) {
            return (int) $existing['membership_id'];
        }

        return $this->create([
            'course_id' => $courseId,
            'user_id' => $userId,
            'role_in_course' => $role,
        ]);
    }
}
