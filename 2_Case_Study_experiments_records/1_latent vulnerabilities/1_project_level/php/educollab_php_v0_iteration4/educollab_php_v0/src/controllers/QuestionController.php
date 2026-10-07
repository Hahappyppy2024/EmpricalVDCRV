<?php
require_once APP_BASE . '/src/repos/QuestionRepo.php';
require_once APP_BASE . '/src/controllers/_guards.php';

class QuestionController {
    public function __construct(private PDO $pdo) {}

    public function list(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        require_course_staff($this->pdo, $courseId, $u, false);
        $repo = new QuestionRepo($this->pdo);
        render('questions/list', ['title' => 'Question Bank', 'course_id' => $courseId, 'questions' => $repo->listByCourse($courseId)]);
    }

    public function showNew(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        require_course_teacher_or_admin($this->pdo, $courseId, $u, false);
        render('questions/new', ['title' => 'New Question', 'course_id' => $courseId]);
    }

    public function create(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        require_course_teacher_or_admin($this->pdo, $courseId, $u, false);
        $qtype = trim($_POST['qtype'] ?? '');
        $prompt = trim($_POST['prompt'] ?? '');
        $optionsJson = trim($_POST['options_json'] ?? '');
        $answerJson = trim($_POST['answer_json'] ?? '');
        $repo = new QuestionRepo($this->pdo);
        $q = $repo->create($courseId, (int)$u['user_id'], $qtype, $prompt, $optionsJson, $answerJson);
        redirect("/courses/$courseId/questions/{$q['question_id']}");
    }

    public function detail(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        $questionId = (int)$p['question_id'];
        require_course_staff($this->pdo, $courseId, $u, false);
        $repo = new QuestionRepo($this->pdo);
        $q = $repo->getById($courseId, $questionId);
        if (!$q) { http_response_code(404); echo 'Not found'; return; }
        render('questions/detail', ['title' => 'Question Detail', 'course_id' => $courseId, 'question' => $q]);
    }

    public function showEdit(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        $questionId = (int)$p['question_id'];
        require_course_teacher_or_admin($this->pdo, $courseId, $u, false);
        $repo = new QuestionRepo($this->pdo);
        $q = $repo->getById($courseId, $questionId);
        if (!$q) { http_response_code(404); echo 'Not found'; return; }
        render('questions/edit', ['title' => 'Edit Question', 'course_id' => $courseId, 'question' => $q]);
    }

    public function update(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        $questionId = (int)$p['question_id'];
        require_course_teacher_or_admin($this->pdo, $courseId, $u, false);
        $qtype = trim($_POST['qtype'] ?? '');
        $prompt = trim($_POST['prompt'] ?? '');
        $optionsJson = trim($_POST['options_json'] ?? '');
        $answerJson = trim($_POST['answer_json'] ?? '');
        $repo = new QuestionRepo($this->pdo);
        $repo->update($courseId, $questionId, $qtype, $prompt, $optionsJson, $answerJson);
        redirect("/courses/$courseId/questions/$questionId");
    }

    public function delete(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        $questionId = (int)$p['question_id'];
        require_course_teacher_or_admin($this->pdo, $courseId, $u, false);
        $repo = new QuestionRepo($this->pdo);
        $repo->delete($courseId, $questionId);
        redirect("/courses/$courseId/questions");
    }

    public function apiList(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        require_course_staff($this->pdo, $courseId, $u, true);
        $repo = new QuestionRepo($this->pdo);
        json_response(['questions' => $repo->listByCourse($courseId)]);
    }

    public function apiCreate(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        require_course_teacher_or_admin($this->pdo, $courseId, $u, true);
        $data = parse_json_body();
        $qtype = trim($data['qtype'] ?? '');
        $prompt = trim($data['prompt'] ?? '');
        $optionsJson = trim($data['options_json'] ?? '');
        $answerJson = trim($data['answer_json'] ?? '');
        $repo = new QuestionRepo($this->pdo);
        $q = $repo->create($courseId, (int)$u['user_id'], $qtype, $prompt, $optionsJson, $answerJson);
        json_response(['question' => $q], 201);
    }

    public function apiGet(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        $questionId = (int)$p['question_id'];
        require_course_staff($this->pdo, $courseId, $u, true);
        $q = (new QuestionRepo($this->pdo))->getById($courseId, $questionId);
        if (!$q) json_response(['error' => 'not found'], 404);
        json_response(['question' => $q]);
    }

    public function apiUpdate(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        $questionId = (int)$p['question_id'];
        require_course_teacher_or_admin($this->pdo, $courseId, $u, true);
        $data = parse_json_body();
        $qtype = trim($data['qtype'] ?? '');
        $prompt = trim($data['prompt'] ?? '');
        $optionsJson = trim($data['options_json'] ?? '');
        $answerJson = trim($data['answer_json'] ?? '');
        $repo = new QuestionRepo($this->pdo);
        $q = $repo->update($courseId, $questionId, $qtype, $prompt, $optionsJson, $answerJson);
        json_response(['question' => $q]);
    }

    public function apiDelete(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        $questionId = (int)$p['question_id'];
        require_course_teacher_or_admin($this->pdo, $courseId, $u, true);
        $repo = new QuestionRepo($this->pdo);
        $repo->delete($courseId, $questionId);
        json_response(['ok' => true]);
    }
}