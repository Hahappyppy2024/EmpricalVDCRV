<?php
require_once APP_BASE . '/src/repos/AssignmentRepo.php';
require_once APP_BASE . '/src/controllers/_guards.php';

class AssignmentController {
    public function __construct(private PDO $pdo) {}

    public function list(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        require_course_member($this->pdo, $courseId, $u, false);
        $repo = new AssignmentRepo($this->pdo);
        render('assignments/list', ['title' => 'Assignments', 'course_id' => $courseId, 'assignments' => $repo->listByCourse($courseId)]);
    }

    public function showNew(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        require_course_staff($this->pdo, $courseId, $u, false);
        render('assignments/new', ['title' => 'New Assignment', 'course_id' => $courseId]);
    }

    public function create(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        require_course_staff($this->pdo, $courseId, $u, false);
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $due = trim($_POST['due_at'] ?? '');
        $due = $due === '' ? null : $due;
        $repo = new AssignmentRepo($this->pdo);
        $a = $repo->create($courseId, (int)$u['user_id'], $title, $desc, $due);
        redirect("/courses/$courseId/assignments/{$a['assignment_id']}");
    }

    public function detail(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        $assignmentId = (int)$p['assignment_id'];
        require_course_member($this->pdo, $courseId, $u, false);
        $repo = new AssignmentRepo($this->pdo);
        $a = $repo->getById($courseId, $assignmentId);
        if (!$a) { http_response_code(404); echo 'Not found'; return; }
        render('assignments/detail', ['title' => $a['title'], 'course_id' => $courseId, 'assignment' => $a]);
    }

    public function showEdit(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        $assignmentId = (int)$p['assignment_id'];
        require_course_staff($this->pdo, $courseId, $u, false);
        $repo = new AssignmentRepo($this->pdo);
        $a = $repo->getById($courseId, $assignmentId);
        if (!$a) { http_response_code(404); echo 'Not found'; return; }
        render('assignments/edit', ['title' => 'Edit Assignment', 'course_id' => $courseId, 'assignment' => $a]);
    }

    public function update(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        $assignmentId = (int)$p['assignment_id'];
        require_course_staff($this->pdo, $courseId, $u, false);
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $due = trim($_POST['due_at'] ?? '');
        $due = $due === '' ? null : $due;
        $repo = new AssignmentRepo($this->pdo);
        $repo->update($courseId, $assignmentId, $title, $desc, $due);
        redirect("/courses/$courseId/assignments/$assignmentId");
    }

    public function delete(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        $assignmentId = (int)$p['assignment_id'];
        require_course_staff($this->pdo, $courseId, $u, false);
        (new AssignmentRepo($this->pdo))->delete($courseId, $assignmentId);
        redirect("/courses/$courseId/assignments");
    }

    // API
    public function apiList(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        require_course_member($this->pdo, $courseId, $u, true);
        $repo = new AssignmentRepo($this->pdo);
        json_response(['assignments' => $repo->listByCourse($courseId)]);
    }

    public function apiCreate(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        require_course_staff($this->pdo, $courseId, $u, true);
        $data = parse_json_body();
        $title = trim($data['title'] ?? '');
        $desc = trim($data['description'] ?? '');
        $due = trim($data['due_at'] ?? '');
        $due = $due === '' ? null : $due;
        $repo = new AssignmentRepo($this->pdo);
        $a = $repo->create($courseId, (int)$u['user_id'], $title, $desc, $due);
        json_response(['assignment' => $a]);
    }

    public function apiGet(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        $assignmentId = (int)$p['assignment_id'];
        require_course_member($this->pdo, $courseId, $u, true);
        $a = (new AssignmentRepo($this->pdo))->getById($courseId, $assignmentId);
        if (!$a) json_response(['error' => 'not found'], 404);
        json_response(['assignment' => $a]);
    }

    public function apiUpdate(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        $assignmentId = (int)$p['assignment_id'];
        require_course_staff($this->pdo, $courseId, $u, true);
        $data = parse_json_body();
        $title = trim($data['title'] ?? '');
        $desc = trim($data['description'] ?? '');
        $due = trim($data['due_at'] ?? '');
        $due = $due === '' ? null : $due;
        $a = (new AssignmentRepo($this->pdo))->update($courseId, $assignmentId, $title, $desc, $due);
        json_response(['assignment' => $a]);
    }

    public function apiDelete(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        $assignmentId = (int)$p['assignment_id'];
        require_course_staff($this->pdo, $courseId, $u, true);
        (new AssignmentRepo($this->pdo))->delete($courseId, $assignmentId);
        json_response(['ok' => true]);
    }
}

