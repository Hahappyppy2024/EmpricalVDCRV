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
        require_course_staff($this->pdo, $courseId, $u, false);
        render('questions/new', ['title' => 'New Question', 'course_id' => $courseId]);
    }

    public function create(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        require_course_staff($this->pdo, $courseId, $u, false);
        $qtype = trim($_POST['qtype'] ?? 'text');
        $prompt = trim($_POST['prompt'] ?? '');
        $options = trim($_POST['options_json'] ?? '');
        $answer = trim($_POST['answer_json'] ?? '');
        $options = $options === '' ? null : $options;
        $answer = $answer === '' ? null : $answer;
        $repo = new QuestionRepo($this->pdo);
        $q = $repo->create($courseId, (int)$u['user_id'], $qtype, $prompt, $options, $answer);
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
        render('questions/detail', ['title' => 'Question', 'course_id' => $courseId, 'question' => $q]);
    }

    public function showEdit(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        $questionId = (int)$p['question_id'];
        require_course_staff($this->pdo, $courseId, $u, false);
        $repo = new QuestionRepo($this->pdo);
        $q = $repo->getById($courseId, $questionId);
        if (!$q) { http_response_code(404); echo 'Not found'; return; }
        render('questions/edit', ['title' => 'Edit Question', 'course_id' => $courseId, 'question' => $q]);
    }

    public function update(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        $questionId = (int)$p['question_id'];
        require_course_staff($this->pdo, $courseId, $u, false);
        $qtype = trim($_POST['qtype'] ?? 'text');
        $prompt = trim($_POST['prompt'] ?? '');
        $options = trim($_POST['options_json'] ?? '');
        $answer = trim($_POST['answer_json'] ?? '');
        $options = $options === '' ? null : $options;
        $answer = $answer === '' ? null : $answer;
        (new QuestionRepo($this->pdo))->update($courseId, $questionId, $qtype, $prompt, $options, $answer);
        redirect("/courses/$courseId/questions/$questionId");
    }

    public function delete(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        $questionId = (int)$p['question_id'];
        require_course_staff($this->pdo, $courseId, $u, false);
        (new QuestionRepo($this->pdo))->delete($courseId, $questionId);
        redirect("/courses/$courseId/questions");
    }

    // API
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
        require_course_staff($this->pdo, $courseId, $u, true);
        $data = parse_json_body();
        $qtype = trim($data['qtype'] ?? 'text');
        $prompt = trim($data['prompt'] ?? '');
        $options = $data['options_json'] ?? null;
        $answer = $data['answer_json'] ?? null;
        $options = ($options === null || $options === '') ? null : (string)$options;
        $answer = ($answer === null || $answer === '') ? null : (string)$answer;
        $q = (new QuestionRepo($this->pdo))->create($courseId, (int)$u['user_id'], $qtype, $prompt, $options, $answer);
        json_response(['question' => $q]);
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
        require_course_staff($this->pdo, $courseId, $u, true);
        $data = parse_json_body();
        $qtype = trim($data['qtype'] ?? 'text');
        $prompt = trim($data['prompt'] ?? '');
        $options = $data['options_json'] ?? null;
        $answer = $data['answer_json'] ?? null;
        $options = ($options === null || $options === '') ? null : (string)$options;
        $answer = ($answer === null || $answer === '') ? null : (string)$answer;
        $q = (new QuestionRepo($this->pdo))->update($courseId, $questionId, $qtype, $prompt, $options, $answer);
        json_response(['question' => $q]);
    }

    public function apiDelete(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        $questionId = (int)$p['question_id'];
        require_course_staff($this->pdo, $courseId, $u, true);
        (new QuestionRepo($this->pdo))->delete($courseId, $questionId);
        json_response(['ok' => true]);
    }
}
