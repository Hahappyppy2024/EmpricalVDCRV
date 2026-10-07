<?php

class MembershipRepo {
    public function __construct(private PDO $pdo) {}

    public function add(int $courseId, int $userId, string $role): array {
        $stmt = $this->pdo->prepare('INSERT INTO memberships(course_id, user_id, role_in_course, joined_at) VALUES (?,?,?,?)');
        $stmt->execute([$courseId, $userId, $role, now_iso()]);
        return $this->getById((int)$this->pdo->lastInsertId());
    }

    public function getById(int $membershipId): ?array {
        $stmt = $this->pdo->prepare('
            SELECT m.*, u.username, u.display_name
            FROM memberships m
            JOIN users u ON u.user_id = m.user_id
            WHERE m.membership_id=?
        ');
        $stmt->execute([$membershipId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getByCourseAndUser(int $courseId, int $userId): ?array {
        $stmt = $this->pdo->prepare('
            SELECT m.*, u.username, u.display_name
            FROM memberships m
            JOIN users u ON u.user_id = m.user_id
            WHERE m.course_id=? AND m.user_id=?
        ');
        $stmt->execute([$courseId, $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function listByCourse(int $courseId): array {
        $stmt = $this->pdo->prepare('
            SELECT m.*, u.username, u.display_name
            FROM memberships m
            JOIN users u ON u.user_id = m.user_id
            WHERE m.course_id=?
            ORDER BY m.membership_id ASC
        ');
        $stmt->execute([$courseId]);
        return $stmt->fetchAll();
    }

    public function updateRole(int $courseId, int $membershipId, string $role): ?array {
        $stmt = $this->pdo->prepare('UPDATE memberships SET role_in_course=? WHERE course_id=? AND membership_id=?');
        $stmt->execute([$role, $courseId, $membershipId]);
        return $this->getById($membershipId);
    }

    public function remove(int $courseId, int $membershipId): void {
        $stmt = $this->pdo->prepare('DELETE FROM memberships WHERE course_id=? AND membership_id=?');
        $stmt->execute([$courseId, $membershipId]);
    }

    public function isMember(int $courseId, int $userId): bool {
        $stmt = $this->pdo->prepare('SELECT 1 FROM memberships WHERE course_id=? AND user_id=?');
        $stmt->execute([$courseId, $userId]);
        return (bool)$stmt->fetchColumn();
    }
}