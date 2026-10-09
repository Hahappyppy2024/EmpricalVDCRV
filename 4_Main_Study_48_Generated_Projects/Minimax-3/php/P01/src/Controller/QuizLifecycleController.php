<?php
declare(strict_types=1);

namespace LMS\Controller;

use LMS\Auth\AuthService;
use LMS\Http\Csrf;
use LMS\Http\Session;
use LMS\Http\View;
use LMS\Repository\CourseRepository;
use LMS\Repository\EnrollmentRepository;
use LMS\Repository\GradeRepository;
use LMS\Repository\QuizRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class QuizLifecycleController
{
    public function __construct(
        private View $view,
        private CourseRepository $courses,
        private QuizRepository $quizzes,
        private EnrollmentRepository $enrollments,
        private GradeRepository $grades,
        private AuthService $auth,
        private Session $session,
        private Csrf $csrf
    ) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->auth->currentUser();
        $course = $this->courses->find((int)$args['id']);
        if (!$course) {
            return $this->view->render($response->withStatus(404), 'error.php', ['error' => 'Course not found.', 'status' => 404]);
        }
        $rows = $this->quizzes->listForCourse((int)$course['id']);
        return $this->view->render($response, 'quizzes.php', [
            'course' => $course,
            'rows' => $rows,
            'user' => $user,
            'csrf' => $this->csrf->token(),
            'canCreate' => in_array($user['role'], ['instructor', 'admin'], true) && ($user['role'] === 'admin' || (int)$course['instructor_id'] === (int)$user['id']),
            'flash' => ['notice' => $this->session->takeFlash('notice'), 'error' => $this->session->takeFlash('error')],
        ]);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->auth->currentUser();
        $course = $this->courses->find((int)$args['id']);
        if (!$course || !in_array($user['role'], ['instructor', 'admin'], true)) {
            return $this->view->render($response->withStatus(403), 'error.php', ['error' => 'Forbidden.', 'status' => 403]);
        }
        $errors = [];
        $input = [
            'title' => '',
            'description' => '',
            'closes_at' => date('Y-m-d\TH:i', strtotime('+7 days')),
            'questions' => [],
        ];
        if (strtoupper($request->getMethod()) === 'POST') {
            $body = (array)$request->getParsedBody();
            $input['title'] = trim((string)($body['title'] ?? ''));
            $input['description'] = trim((string)($body['description'] ?? ''));
            $input['closes_at'] = str_replace('T', ' ', (string)($body['closes_at'] ?? '')) . ':00';
            $input['questions'] = (array)($body['questions'] ?? []);

            if ($input['title'] === '') $errors['title'] = 'Title is required.';
            $validQuestions = [];
            foreach ($input['questions'] as $i => $q) {
                $prompt = trim((string)($q['prompt'] ?? ''));
                $kind = (string)($q['kind'] ?? 'single');
                if (!in_array($kind, ['single', 'multi', 'short'], true)) {
                    $errors["q$i"] = 'Invalid question kind.';
                    continue;
                }
                if ($prompt === '') {
                    $errors["q$i"] = 'Prompt is required.';
                    continue;
                }
                $options = $kind === 'short' ? [] : array_values(array_filter(array_map('trim', (array)($q['options'] ?? [])), fn($v) => $v !== ''));
                $correct = $kind === 'short'
                    ? [trim((string)($q['correct'] ?? ''))]
                    : (array)($q['correct'] ?? []);
                $correct = array_values(array_filter(array_map('trim', $correct), fn($v) => $v !== ''));
                if ($kind !== 'short' && count($options) < 2) {
                    $errors["q$i"] = 'Provide at least two options.';
                    continue;
                }
                if (empty($correct)) {
                    $errors["q$i"] = 'Provide a correct answer.';
                    continue;
                }
                $validQuestions[] = [
                    'prompt' => $prompt,
                    'kind' => $kind,
                    'options' => $options,
                    'correct' => $correct,
                    'points' => (float)($q['points'] ?? 1),
                ];
            }
            if (empty($validQuestions)) {
                $errors['questions'] = 'Add at least one valid question.';
            }
            if (empty($errors)) {
                $quizId = $this->quizzes->createQuiz([
                    'course_id' => (int)$course['id'],
                    'title' => $input['title'],
                    'description' => $input['description'],
                    'created_by' => (int)$user['id'],
                    'closes_at' => $input['closes_at'],
                ], $validQuestions);
                $this->session->setFlash('notice', 'Quiz created.');
                return $response->withHeader('Location', '/courses/' . $course['id'] . '/quizzes')->withStatus(302);
            }
            $input['questions'] = $this->normaliseQuestionsForRepost($input['questions']);
        }
        return $this->view->render($response, 'quiz_form.php', [
            'course' => $course,
            'input' => $input,
            'errors' => $errors,
            'csrf' => $this->csrf->token(),
            'user' => $user,
        ]);
    }

    public function take(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->auth->currentUser();
        $quiz = $this->quizzes->findWithQuestions((int)$args['id']);
        if (!$quiz) {
            return $this->view->render($response->withStatus(404), 'error.php', ['error' => 'Quiz not found.', 'status' => 404]);
        }
        if (!$this->enrollments->isEnrolled((int)$user['id'], (int)$quiz['course_id'])) {
            return $this->view->render($response->withStatus(403), 'error.php', ['error' => 'Not enrolled.', 'status' => 403]);
        }
        if (strtoupper($request->getMethod()) === 'POST') {
            $action = (string)((array)$request->getParsedBody())['action'] ?? 'save';
            $attemptResult = $this->quizzes->startAttempt((int)$quiz['id'], (int)$user['id']);
            $attempt = $attemptResult['attempt'];
            $body = (array)$request->getParsedBody();
            $answers = (array)($body['answers'] ?? []);
            $this->quizzes->saveAnswers((int)$attempt['id'], $answers);
            if ($action === 'submit') {
                $final = $this->quizzes->submitAttempt((int)$attempt['id']);
                $this->grades->create([
                    'course_id' => (int)$quiz['course_id'],
                    'student_id' => (int)$user['id'],
                    'item_type' => 'quiz',
                    'item_id' => (int)$quiz['id'],
                    'item_label' => $quiz['title'],
                    'score' => $final['score'] ?? 0,
                    'max_score' => $final['max_score'] ?? 0,
                    'feedback' => null,
                    'graded_by' => (int)$user['id'],
                ]);
                $this->session->setFlash('notice', 'Quiz submitted. Score: ' . ($final['score'] ?? 0));
                return $response->withHeader('Location', '/courses/' . $quiz['course_id'] . '/quizzes')->withStatus(302);
            }
            $this->session->setFlash('notice', 'Progress saved.');
            return $response->withHeader('Location', '/quizzes/' . $quiz['id'] . '/take')->withStatus(302);
        }
        return $this->view->render($response, 'quiz_take.php', [
            'quiz' => $quiz,
            'user' => $user,
            'csrf' => $this->csrf->token(),
            'attempt' => $this->quizzes->latestAttempt((int)$quiz['id'], (int)$user['id']),
        ]);
    }

    public function attempts(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $quiz = $this->quizzes->find((int)$args['id']);
        if (!$quiz) {
            return $this->view->render($response->withStatus(404), 'error.php', ['error' => 'Quiz not found.', 'status' => 404]);
        }
        $rows = $this->quizzes->listAttempts((int)$quiz['id']);
        return $this->view->render($response, 'quiz_attempts.php', [
            'quiz' => $quiz,
            'rows' => $rows,
            'user' => $this->auth->currentUser(),
        ]);
    }

    private function normaliseQuestionsForRepost(array $questions): array
    {
        return array_map(function ($q) {
            $options = (array)($q['options'] ?? []);
            if (isset($options[0]) && is_array($options[0])) {
                $flat = [];
                foreach ($options as $opt) {
                    $flat[] = (string)($opt ?? '');
                }
                $q['options'] = $flat;
            }
            return $q;
        }, $questions);
    }
}
