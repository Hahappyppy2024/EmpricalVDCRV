<?php

class MembershipRepo {
    public function __construct(private PDO $pdo) {}

    public function add(int $courseId, int $userId, string $role): array {
        $stmt = $this->pdo->prepare('INSERT OR REPLACE INTO memberships(course_id,user_id,role_in_course,created_at) VALUES (?,?,?,COALESCE((SELECT created_at FROM memberships WHERE course_id=? AND user_id=?), ?))');
        $now = now_iso();
        $stmt->execute([$courseId, $userId, $role, $courseId, $userId, $now]);
        return $this->getByCourseAndUser($courseId, $userId);
    }

    public function listByCourse(int $courseId): array {
        $stmt = $this->pdo->prepare('SELECT m.*, u.username, u.display_name FROM memberships m JOIN users u ON u.user_id=m.user_id WHERE m.course_id=? ORDER BY m.membership_id DESC');
        $stmt->execute([$courseId]);
        return $stmt->fetchAll();
    }

    public function getById(int $membershipId): ?array {
        $stmt = $this->pdo->prepare('SELECT m.*, u.username, u.display_name FROM memberships m JOIN users u ON u.user_id=m.user_id WHERE m.membership_id=?');
        $stmt->execute([$membershipId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getByCourseAndUser(int $courseId, int $userId): ?array {
        $stmt = $this->pdo->prepare('SELECT * FROM memberships WHERE course_id=? AND user_id=?');
        $stmt->execute([$courseId, $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function myMemberships(int $userId): array {
        $stmt = $this->pdo->prepare('SELECT m.*, c.title AS course_title FROM memberships m JOIN courses c ON c.course_id=m.course_id WHERE m.user_id=? ORDER BY m.membership_id DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function updateRole(int $membershipId, string $role): ?array {
        $stmt = $this->pdo->prepare('UPDATE memberships SET role_in_course=? WHERE membership_id=?');
        $stmt->execute([$role, $membershipId]);
        return $this->getById($membershipId);
    }

    public function remove(int $membershipId): void {
        $stmt = $this->pdo->prepare('DELETE FROM memberships WHERE membership_id=?');
        $stmt->execute([$membershipId]);
    }

    public function isMember(int $courseId, int $userId): bool {
        return $this->getByCourseAndUser($courseId, $userId) !== null;
    }

    public function roleInCourse(int $courseId, int $userId): ?string {
        $m = $this->getByCourseAndUser($courseId, $userId);
        return $m['role_in_course'] ?? null;
    }
}

