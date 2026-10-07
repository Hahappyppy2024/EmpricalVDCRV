<?php
require_once APP_BASE . '/src/repos/MembershipRepo.php';
require_once APP_BASE . '/src/repos/UserRepo.php';
require_once APP_BASE . '/src/controllers/_guards.php';

class MembershipController {
    public function __construct(private PDO $pdo) {}

    public function list(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        require_course_member($this->pdo, $courseId, $u, false);
        $repo = new MembershipRepo($this->pdo);
        render('memberships/list', ['title' => 'Members', 'course_id' => $courseId, 'memberships' => $repo->listByCourse($courseId)]);
    }

    public function showNew(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        require_course_teacher_or_admin($this->pdo, $courseId, $u, false);
        $users = (new UserRepo($this->pdo))->listAll();
        render('memberships/new', ['title' => 'Add Member', 'course_id' => $courseId, 'users' => $users]);
    }

    public function add(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        require_course_teacher_or_admin($this->pdo, $courseId, $u, false);
        $userId = (int)($_POST['user_id'] ?? 0);
        $role = trim($_POST['role_in_course'] ?? 'student');
        $repo = new MembershipRepo($this->pdo);
        $repo->add($courseId, $userId, $role);
        redirect("/courses/$courseId/members");
    }

    public function updateRole(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        $membershipId = (int)$p['membership_id'];
        require_course_teacher_or_admin($this->pdo, $courseId, $u, false);
        $role = trim($_POST['role_in_course'] ?? 'student');
        $repo = new MembershipRepo($this->pdo);
        $m = $repo->getById($membershipId);
        if (!$m || (int)$m['course_id'] !== $courseId) { http_response_code(404); echo 'Not found'; return; }
        $repo->updateRole($courseId, $membershipId, $role);
        redirect("/courses/$courseId/members");
    }

    public function remove(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        $membershipId = (int)$p['membership_id'];
        require_course_teacher_or_admin($this->pdo, $courseId, $u, false);
        $repo = new MembershipRepo($this->pdo);
        $m = $repo->getById($membershipId);
        if (!$m || (int)$m['course_id'] !== $courseId) { http_response_code(404); echo 'Not found'; return; }
        $repo->remove($courseId, $membershipId);
        redirect("/courses/$courseId/members");
    }

    public function myMemberships(): void {
        $u = require_login();
        $repo = new MembershipRepo($this->pdo);
        render('memberships/my', ['title' => 'My Memberships', 'memberships' => $repo->myMemberships((int)$u['user_id'])]);
    }

    // API
    public function apiList(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        require_course_member($this->pdo, $courseId, $u, true);
        $repo = new MembershipRepo($this->pdo);
        json_response(['members' => $repo->listByCourse($courseId)]);
    }

    public function apiAdd(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        require_course_teacher_or_admin($this->pdo, $courseId, $u, true);
        $data = parse_json_body();
        $userId = (int)($data['user_id'] ?? 0);
        $role = trim($data['role_in_course'] ?? 'student');
        $repo = new MembershipRepo($this->pdo);
        $m = $repo->add($courseId, $userId, $role);
        json_response(['membership' => $m]);
    }

    public function apiUpdateRole(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        $membershipId = (int)$p['membership_id'];
        require_course_teacher_or_admin($this->pdo, $courseId, $u, true);
        $data = parse_json_body();
        $role = trim($data['role_in_course'] ?? 'student');
        $repo = new MembershipRepo($this->pdo);
        $existing = $repo->getById($membershipId);
        if (!$existing || (int)$existing['course_id'] !== $courseId) json_response(['error' => 'not found'], 404);
        $m = $repo->updateRole($courseId, $membershipId, $role);
        json_response(['membership' => $m]);
    }

    public function apiRemove(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        $membershipId = (int)$p['membership_id'];
        require_course_teacher_or_admin($this->pdo, $courseId, $u, true);
        $repo = new MembershipRepo($this->pdo);
        $existing = $repo->getById($membershipId);
        if (!$existing || (int)$existing['course_id'] !== $courseId) json_response(['error' => 'not found'], 404);
        $repo->remove($courseId, $membershipId);
        json_response(['ok' => true]);
    }

    public function apiMyMemberships(): void {
        $u = require_api_login();
        $repo = new MembershipRepo($this->pdo);
        json_response(['memberships' => $repo->myMemberships((int)$u['user_id'])]);
    }
}