<?php
require_once APP_BASE . '/src/repos/SubmissionRepo.php';
require_once APP_BASE . '/src/repos/AssignmentRepo.php';
require_once APP_BASE . '/src/repos/UploadRepo.php';
require_once APP_BASE . '/src/controllers/_guards.php';

class SubmissionController {
    public function __construct(private PDO $pdo) {}

    public function showSubmit(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        $assignmentId = (int)$p['assignment_id'];
        require_course_student($this->pdo, $courseId, $u, false);
        $arepo = new AssignmentRepo($this->pdo);
        $a = $arepo->getById($courseId, $assignmentId);
        if (!$a) { http_response_code(404); echo 'Not found'; return; }
        $srepo = new SubmissionRepo($this->pdo);
        $mine = $srepo->getMine($courseId, $assignmentId, (int)$u['user_id']);
        $uploads = (new UploadRepo($this->pdo))->listByCourse($courseId);
        render('submissions/submit', ['title' => 'Submit', 'course_id' => $courseId, 'assignment' => $a, 'submission' => $mine, 'uploads' => $uploads]);
    }

    public function create(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        $assignmentId = (int)$p['assignment_id'];
        require_course_student($this->pdo, $courseId, $u, false);
        $content = trim($_POST['content_text'] ?? '');
        $att = trim($_POST['attachment_upload_id'] ?? '');
        $attId = $att === '' ? null : (int)$att;
        $repo = new SubmissionRepo($this->pdo);
        $repo->create($courseId, $assignmentId, (int)$u['user_id'], $content, $attId);
        redirect("/courses/$courseId/assignments/$assignmentId");
    }

    public function updateMine(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        $assignmentId = (int)$p['assignment_id'];
        $submissionId = (int)$p['submission_id'];
        require_course_student($this->pdo, $courseId, $u, false);
        $content = trim($_POST['content_text'] ?? '');
        $att = trim($_POST['attachment_upload_id'] ?? '');
        $attId = $att === '' ? null : (int)$att;
        $repo = new SubmissionRepo($this->pdo);
        $repo->update($courseId, $assignmentId, $submissionId, (int)$u['user_id'], $content, $attId);
        redirect("/courses/$courseId/assignments/$assignmentId");
    }

    public function listForAssignment(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        $assignmentId = (int)$p['assignment_id'];
        require_course_staff($this->pdo, $courseId, $u, false);
        $repo = new SubmissionRepo($this->pdo);
        $subs = $repo->listForAssignment($courseId, $assignmentId);
        render('submissions/list_for_assignment', ['title' => 'Submissions', 'course_id' => $courseId, 'assignment_id' => $assignmentId, 'submissions' => $subs]);
    }

    public function mySubmissions(): void {
        $u = require_login();
        $repo = new SubmissionRepo($this->pdo);
        render('submissions/my', ['title' => 'My Submissions', 'submissions' => $repo->listMine((int)$u['user_id'])]);
    }

    // API
    public function apiCreate(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        $assignmentId = (int)$p['assignment_id'];
        require_course_student($this->pdo, $courseId, $u, true);
        $data = parse_json_body();
        $content = trim($data['content_text'] ?? '');
        $att = $data['attachment_upload_id'] ?? null;
        $attId = ($att === null || $att === '') ? null : (int)$att;
        $s = (new SubmissionRepo($this->pdo))->create($courseId, $assignmentId, (int)$u['user_id'], $content, $attId);
        json_response(['submission' => $s]);
    }

    public function apiUpdate(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        $assignmentId = (int)$p['assignment_id'];
        $submissionId = (int)$p['submission_id'];
        require_course_student($this->pdo, $courseId, $u, true);
        $data = parse_json_body();
        $content = trim($data['content_text'] ?? '');
        $att = $data['attachment_upload_id'] ?? null;
        $attId = ($att === null || $att === '') ? null : (int)$att;
        $s = (new SubmissionRepo($this->pdo))->update($courseId, $assignmentId, $submissionId, (int)$u['user_id'], $content, $attId);
        json_response(['submission' => $s]);
    }

    public function apiListMine(): void {
        $u = require_api_login();
        $repo = new SubmissionRepo($this->pdo);
        json_response(['submissions' => $repo->listMine((int)$u['user_id'])]);
    }

    public function apiListForAssignment(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        $assignmentId = (int)$p['assignment_id'];
        require_course_staff($this->pdo, $courseId, $u, true);
        $repo = new SubmissionRepo($this->pdo);
        json_response(['submissions' => $repo->listForAssignment($courseId, $assignmentId)]);
    }
}

