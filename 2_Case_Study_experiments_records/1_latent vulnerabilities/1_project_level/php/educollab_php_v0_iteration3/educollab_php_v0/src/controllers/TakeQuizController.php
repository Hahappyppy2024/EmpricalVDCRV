<?php
require_once APP_BASE . '/src/repos/QuizRepo.php';
require_once APP_BASE . '/src/controllers/_guards.php';

class TakeQuizController {
    public function __construct(private PDO $pdo) {}

    private function loadAuthorizedAttempt(QuizRepo $repo, int $courseId, int $quizId, int $attemptId, array $u, bool $api): array {
        $attempt = $repo->getAttemptById($attemptId);
        if (!$attempt || (int)$attempt['student_id'] !== (int)$u['user_id'] || (int)$attempt['quiz_id'] !== $quizId || (int)$attempt['course_id'] !== $courseId) {
            if ($api) json_response(['error' => 'not found'], 404);
            http_response_code(404);
            echo 'Not found';
            exit;
        }
        return $attempt;
    }

    public function start(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        $quizId = (int)$p['quiz_id'];
        require_course_student($this->pdo, $courseId, $u, false);
        $repo = new QuizRepo($this->pdo);
        $quiz = $repo->getById($courseId, $quizId);
        if (!$quiz) { http_response_code(404); echo 'Not found'; return; }
        $attempt = $repo->startAttempt($courseId, $quizId, (int)$u['user_id']);
        redirect("/courses/$courseId/quizzes/$quizId/attempts/{$attempt['attempt_id']}");
    }

    public function showAttempt(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        $quizId = (int)$p['quiz_id'];
        $attemptId = (int)$p['attempt_id'];
        require_course_student($this->pdo, $courseId, $u, false);
        $repo = new QuizRepo($this->pdo);
        $attempt = $this->loadAuthorizedAttempt($repo, $courseId, $quizId, $attemptId, $u, false);
        $quizQuestions = $repo->listQuizQuestions($quizId);
        $answers = $repo->listAnswers($attemptId);
        $map = [];
        foreach ($answers as $a) $map[(int)$a['question_id']] = $a['answer_json'];
        render('quizzes/attempt', ['title' => 'Attempt', 'course_id' => $courseId, 'quiz_id' => $quizId, 'attempt' => $attempt, 'quiz_questions' => $quizQuestions, 'answer_map' => $map]);
    }

    public function answer(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        $quizId = (int)$p['quiz_id'];
        $attemptId = (int)$p['attempt_id'];
        require_course_student($this->pdo, $courseId, $u, false);
        $questionId = (int)($_POST['question_id'] ?? 0);
        $answerJson = trim($_POST['answer_json'] ?? '');
        $repo = new QuizRepo($this->pdo);
        $attempt = $this->loadAuthorizedAttempt($repo, $courseId, $quizId, $attemptId, $u, false);
        if ($attempt['submitted_at']) { http_response_code(403); echo 'Forbidden'; return; }
        if (!$repo->quizHasQuestion($quizId, $questionId)) { http_response_code(404); echo 'Not found'; return; }
        $repo->upsertAnswer($attemptId, $questionId, $answerJson);
        redirect("/courses/$courseId/quizzes/$quizId/attempts/$attemptId");
    }

    public function submit(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        $quizId = (int)$p['quiz_id'];
        $attemptId = (int)$p['attempt_id'];
        require_course_student($this->pdo, $courseId, $u, false);
        $repo = new QuizRepo($this->pdo);
        $attempt = $this->loadAuthorizedAttempt($repo, $courseId, $quizId, $attemptId, $u, false);
        if ($attempt['submitted_at']) { http_response_code(403); echo 'Forbidden'; return; }
        $repo->submitAttempt($attemptId);
        redirect("/courses/$courseId/quizzes/$quizId");
    }

    public function myAttempts(): void {
        $u = require_login();
        $repo = new QuizRepo($this->pdo);
        render('quizzes/my_attempts', ['title' => 'My Quiz Attempts', 'attempts' => $repo->listMyAttempts((int)$u['user_id'])]);
    }

    public function apiStart(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        $quizId = (int)$p['quiz_id'];
        require_course_student($this->pdo, $courseId, $u, true);
        $repo = new QuizRepo($this->pdo);
        $quiz = $repo->getById($courseId, $quizId);
        if (!$quiz) json_response(['error' => 'not found'], 404);
        $attempt = $repo->startAttempt($courseId, $quizId, (int)$u['user_id']);
        json_response(['attempt' => $attempt]);
    }

    public function apiGetAttempt(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        $quizId = (int)$p['quiz_id'];
        $attemptId = (int)$p['attempt_id'];
        require_course_student($this->pdo, $courseId, $u, true);
        $repo = new QuizRepo($this->pdo);
        $attempt = $this->loadAuthorizedAttempt($repo, $courseId, $quizId, $attemptId, $u, true);
        json_response(['attempt' => $attempt, 'answers' => $repo->listAnswers($attemptId)]);
    }

    public function apiAnswer(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        $quizId = (int)$p['quiz_id'];
        $attemptId = (int)$p['attempt_id'];
        require_course_student($this->pdo, $courseId, $u, true);
        $data = parse_json_body();
        $questionId = (int)($data['question_id'] ?? 0);
        $answerJson = (string)($data['answer_json'] ?? '');
        $repo = new QuizRepo($this->pdo);
        $attempt = $this->loadAuthorizedAttempt($repo, $courseId, $quizId, $attemptId, $u, true);
        if ($attempt['submitted_at']) json_response(['error' => 'forbidden'], 403);
        if (!$repo->quizHasQuestion($quizId, $questionId)) json_response(['error' => 'not found'], 404);
        $repo->upsertAnswer($attemptId, $questionId, $answerJson);
        json_response(['ok' => true]);
    }

    public function apiSubmit(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        $quizId = (int)$p['quiz_id'];
        $attemptId = (int)$p['attempt_id'];
        require_course_student($this->pdo, $courseId, $u, true);
        $repo = new QuizRepo($this->pdo);
        $attempt = $this->loadAuthorizedAttempt($repo, $courseId, $quizId, $attemptId, $u, true);
        if ($attempt['submitted_at']) json_response(['error' => 'forbidden'], 403);
        $repo->submitAttempt($attemptId);
        json_response(['ok' => true]);
    }

    public function apiMyAttempts(): void {
        $u = require_api_login();
        $repo = new QuizRepo($this->pdo);
        json_response(['attempts' => $repo->listMyAttempts((int)$u['user_id'])]);
    }
}