<?php
require_once APP_BASE . '/src/repos/QuizRepo.php';
require_once APP_BASE . '/src/repos/QuestionRepo.php';
require_once APP_BASE . '/src/controllers/_guards.php';

class QuizController {
    public function __construct(private PDO $pdo) {}

    private function requireQuizAndQuestionInCourseOrFail(int $courseId, int $quizId, int $questionId, bool $api): void {
        $zrepo = new QuizRepo($this->pdo);
        $qrepo = new QuestionRepo($this->pdo);

        $quiz = $zrepo->getById($courseId, $quizId);
        $question = $qrepo->getById($courseId, $questionId);

        if ($quiz && $question) {
            return;
        }

        if ($api) {
            json_response(['error' => 'not found'], 404);
        }

        http_response_code(404);
        echo 'Not found';
        exit;
    }

    public function list(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        require_course_member($this->pdo, $courseId, $u, false);
        $repo = new QuizRepo($this->pdo);
        render('quizzes/list', ['title' => 'Quizzes', 'course_id' => $courseId, 'quizzes' => $repo->listByCourse($courseId)]);
    }

    public function showNew(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        require_course_staff($this->pdo, $courseId, $u, false);
        render('quizzes/new', ['title' => 'New Quiz', 'course_id' => $courseId]);
    }

    public function create(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        require_course_staff($this->pdo, $courseId, $u, false);
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $open = trim($_POST['open_at'] ?? '');
        $due = trim($_POST['due_at'] ?? '');
        $open = $open === '' ? null : $open;
        $due = $due === '' ? null : $due;
        $z = (new QuizRepo($this->pdo))->create($courseId, (int)$u['user_id'], $title, $desc, $open, $due);
        redirect("/courses/$courseId/quizzes/{$z['quiz_id']}");
    }

    public function detail(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        $quizId = (int)$p['quiz_id'];
        require_course_member($this->pdo, $courseId, $u, false);
        $repo = new QuizRepo($this->pdo);
        $z = $repo->getById($courseId, $quizId);
        if (!$z) { http_response_code(404); echo 'Not found'; return; }
        $qzs = $repo->listQuizQuestions($quizId);
        render('quizzes/detail', ['title' => $z['title'], 'course_id' => $courseId, 'quiz' => $z, 'quiz_questions' => $qzs]);
    }

    public function showEdit(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        $quizId = (int)$p['quiz_id'];
        require_course_staff($this->pdo, $courseId, $u, false);
        $repo = new QuizRepo($this->pdo);
        $z = $repo->getById($courseId, $quizId);
        if (!$z) { http_response_code(404); echo 'Not found'; return; }
        render('quizzes/edit', ['title' => 'Edit Quiz', 'course_id' => $courseId, 'quiz' => $z]);
    }

    public function update(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        $quizId = (int)$p['quiz_id'];
        require_course_staff($this->pdo, $courseId, $u, false);
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $open = trim($_POST['open_at'] ?? '');
        $due = trim($_POST['due_at'] ?? '');
        $open = $open === '' ? null : $open;
        $due = $due === '' ? null : $due;
        (new QuizRepo($this->pdo))->update($courseId, $quizId, $title, $desc, $open, $due);
        redirect("/courses/$courseId/quizzes/$quizId");
    }

    public function delete(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        $quizId = (int)$p['quiz_id'];
        require_course_staff($this->pdo, $courseId, $u, false);
        (new QuizRepo($this->pdo))->delete($courseId, $quizId);
        redirect("/courses/$courseId/quizzes");
    }

    public function showConfigureQuestions(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        $quizId = (int)$p['quiz_id'];
        require_course_staff($this->pdo, $courseId, $u, false);
        $zrepo = new QuizRepo($this->pdo);
        $qrepo = new QuestionRepo($this->pdo);
        $quiz = $zrepo->getById($courseId, $quizId);
        if (!$quiz) { http_response_code(404); echo 'Not found'; return; }
        $available = $qrepo->listByCourse($courseId);
        $current = $zrepo->listQuizQuestions($quizId);
        render('quizzes/configure_questions', ['title' => 'Configure Quiz Questions', 'course_id' => $courseId, 'quiz' => $quiz, 'available' => $available, 'current' => $current]);
    }

    public function addQuizQuestion(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        $quizId = (int)$p['quiz_id'];
        require_course_staff($this->pdo, $courseId, $u, false);
        $questionId = (int)($_POST['question_id'] ?? 0);
        $points = (int)($_POST['points'] ?? 1);
        $position = (int)($_POST['position'] ?? 1);
        $this->requireQuizAndQuestionInCourseOrFail($courseId, $quizId, $questionId, false);
        (new QuizRepo($this->pdo))->addQuizQuestion($quizId, $questionId, $points, $position);
        redirect("/courses/$courseId/quizzes/$quizId/questions");
    }

    public function removeQuizQuestion(array $p): void {
        $u = require_login();
        $courseId = (int)$p['course_id'];
        $quizId = (int)$p['quiz_id'];
        $questionId = (int)$p['question_id'];
        require_course_staff($this->pdo, $courseId, $u, false);
        (new QuizRepo($this->pdo))->removeQuizQuestion($quizId, $questionId);
        redirect("/courses/$courseId/quizzes/$quizId/questions");
    }

    // API
    public function apiList(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        require_course_member($this->pdo, $courseId, $u, true);
        json_response(['quizzes' => (new QuizRepo($this->pdo))->listByCourse($courseId)]);
    }

    public function apiCreate(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        require_course_staff($this->pdo, $courseId, $u, true);
        $data = parse_json_body();
        $title = trim($data['title'] ?? '');
        $desc = trim($data['description'] ?? '');
        $open = trim($data['open_at'] ?? '');
        $due = trim($data['due_at'] ?? '');
        $open = $open === '' ? null : $open;
        $due = $due === '' ? null : $due;
        $z = (new QuizRepo($this->pdo))->create($courseId, (int)$u['user_id'], $title, $desc, $open, $due);
        json_response(['quiz' => $z]);
    }

    public function apiGet(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        $quizId = (int)$p['quiz_id'];
        require_course_member($this->pdo, $courseId, $u, true);
        $repo = new QuizRepo($this->pdo);
        $z = $repo->getById($courseId, $quizId);
        if (!$z) json_response(['error' => 'not found'], 404);
        json_response(['quiz' => $z, 'quiz_questions' => $repo->listQuizQuestions($quizId)]);
    }

    public function apiUpdate(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        $quizId = (int)$p['quiz_id'];
        require_course_staff($this->pdo, $courseId, $u, true);
        $data = parse_json_body();
        $title = trim($data['title'] ?? '');
        $desc = trim($data['description'] ?? '');
        $open = trim($data['open_at'] ?? '');
        $due = trim($data['due_at'] ?? '');
        $open = $open === '' ? null : $open;
        $due = $due === '' ? null : $due;
        $z = (new QuizRepo($this->pdo))->update($courseId, $quizId, $title, $desc, $open, $due);
        json_response(['quiz' => $z]);
    }

    public function apiDelete(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        $quizId = (int)$p['quiz_id'];
        require_course_staff($this->pdo, $courseId, $u, true);
        (new QuizRepo($this->pdo))->delete($courseId, $quizId);
        json_response(['ok' => true]);
    }

    public function apiAddQuizQuestion(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        $quizId = (int)$p['quiz_id'];
        require_course_staff($this->pdo, $courseId, $u, true);
        $data = parse_json_body();
        $questionId = (int)($data['question_id'] ?? 0);
        $points = (int)($data['points'] ?? 1);
        $position = (int)($data['position'] ?? 1);
        $this->requireQuizAndQuestionInCourseOrFail($courseId, $quizId, $questionId, true);
        (new QuizRepo($this->pdo))->addQuizQuestion($quizId, $questionId, $points, $position);
        json_response(['ok' => true]);
    }

    public function apiRemoveQuizQuestion(array $p): void {
        $u = require_api_login();
        $courseId = (int)$p['course_id'];
        $quizId = (int)$p['quiz_id'];
        $questionId = (int)$p['question_id'];
        require_course_staff($this->pdo, $courseId, $u, true);
        (new QuizRepo($this->pdo))->removeQuizQuestion($quizId, $questionId);
        json_response(['ok' => true]);
    }
}