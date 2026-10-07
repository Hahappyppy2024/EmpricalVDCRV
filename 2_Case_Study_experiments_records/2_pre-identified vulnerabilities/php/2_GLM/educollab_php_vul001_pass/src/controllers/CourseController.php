<?php
require_once APP_BASE . '/src/repos/CourseRepo.php';
require_once APP_BASE . '/src/repos/MembershipRepo.php';
require_once APP_BASE . '/src/controllers/_guards.php';

class CourseController {
    public function __construct(private PDO $pdo) {}

    public function list(): void {
        require_login();
        $repo = new CourseRepo($this->pdo);
        render('courses/list', ['title' => 'Courses', 'courses' => $repo->listAll()]);
    }

    public function showNew(): void {
        require_login();
        render('courses/new', ['title' => 'New Course']);
    }

    public function create(): void {
        $u = require_login();
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if ($title === '') {
            render('courses/new', ['title' => 'New Course', 'error' => 'title required']);
            return;
        }
        $repo = new CourseRepo($this->pdo);
        $course = $repo->create((int)$u['user_id'], $title, $desc);
        // Auto add creator as teacher (or admin for global admin)
        $mrepo = new MembershipRepo($this->pdo);
        $role = is_global_admin($u) ? 'admin' : 'teacher';
        $mrepo->add((int)$course['course_id'], (int)$u['user_id'], $role);
        redirect('/courses/' . $course['course_id']);
    }

    public function detail(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        $repo = new CourseRepo($this->pdo);
        $course = $repo->getById($courseId);
        if (!$course) { http_response_code(404); echo 'Not found'; return; }
        // allow viewing course detail even if not member? keep simple: require membership
        require_course_member($this->pdo, $courseId, $u, false);
        render('courses/detail', ['title' => 'Course', 'course' => $course]);
    }

    public function showEdit(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        require_course_teacher_or_admin($this->pdo, $courseId, $u, false);
        $repo = new CourseRepo($this->pdo);
        $course = $repo->getById($courseId);
        if (!$course) { http_response_code(404); echo 'Not found'; return; }
        render('courses/edit', ['title' => 'Edit Course', 'course' => $course]);
    }

    public function update(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        require_course_teacher_or_admin($this->pdo, $courseId, $u, false);
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $repo = new CourseRepo($this->pdo);
        $repo->update($courseId, $title, $desc);
        redirect('/courses/' . $courseId);
    }

    public function delete(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        require_course_teacher_or_admin($this->pdo, $courseId, $u, false);
        $repo = new CourseRepo($this->pdo);
        $repo->delete($courseId);
        redirect('/courses');
    }

    // API
    public function apiList(): void {
        require_api_login();
        $repo = new CourseRepo($this->pdo);
        json_response(['courses' => $repo->listAll()]);
    }

    public function apiCreate(): void {
        $u = require_api_login();
        $data = parse_json_body();
        $title = trim($data['title'] ?? '');
        $desc = trim($data['description'] ?? '');
        if ($title === '') json_response(['error' => 'title required'], 400);
        $repo = new CourseRepo($this->pdo);
        $course = $repo->create((int)$u['user_id'], $title, $desc);
        $mrepo = new MembershipRepo($this->pdo);
        $role = is_global_admin($u) ? 'admin' : 'teacher';
        $mrepo->add((int)$course['course_id'], (int)$u['user_id'], $role);
        json_response(['course' => $course]);
    }

    public function apiGet(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        require_course_member($this->pdo, $courseId, $u, true);
        $repo = new CourseRepo($this->pdo);
        $course = $repo->getById($courseId);
        if (!$course) json_response(['error' => 'not found'], 404);
        json_response(['course' => $course]);
    }

    public function apiUpdate(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        require_course_teacher_or_admin($this->pdo, $courseId, $u, true);
        $data = parse_json_body();
        $title = trim($data['title'] ?? '');
        $desc = trim($data['description'] ?? '');
        $repo = new CourseRepo($this->pdo);
        $course = $repo->update($courseId, $title, $desc);
        json_response(['course' => $course]);
    }

    public function apiDelete(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        require_course_teacher_or_admin($this->pdo, $courseId, $u, true);
        $repo = new CourseRepo($this->pdo);
        $repo->delete($courseId);
        json_response(['ok' => true]);
    }
}

