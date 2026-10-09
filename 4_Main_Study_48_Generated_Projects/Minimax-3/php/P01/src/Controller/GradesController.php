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
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class GradesController
{
    public function __construct(
        private View $view,
        private GradeRepository $grades,
        private CourseRepository $courses,
        private EnrollmentRepository $enrollments,
        private AuthService $auth,
        private Session $session,
        private Csrf $csrf
    ) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $this->auth->currentUser();
        if ($user['role'] === 'student') {
            $rows = $this->grades->forStudent((int)$user['id']);
            return $this->view->render($response, 'grades_student.php', [
                'rows' => $rows,
                'user' => $user,
            ]);
        }
        $courses = $this->courses->listForInstructor((int)$user['id']);
        if ($user['role'] === 'admin') {
            $courses = $this->courses->search([], true);
        }
        return $this->view->render($response, 'grades_instructor.php', [
            'courses' => $courses,
            'user' => $user,
            'csrf' => $this->csrf->token(),
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
        $input = ['student_id' => '', 'item_label' => '', 'score' => '', 'max_score' => '100', 'feedback' => ''];
        $roster = $this->enrollments->roster((int)$course['id']);
        if (strtoupper($request->getMethod()) === 'POST') {
            $body = (array)$request->getParsedBody();
            $input['student_id'] = (int)($body['student_id'] ?? 0);
            $input['item_label'] = trim((string)($body['item_label'] ?? ''));
            $input['score'] = (float)($body['score'] ?? -1);
            $input['max_score'] = (float)($body['max_score'] ?? 100);
            $input['feedback'] = trim((string)($body['feedback'] ?? ''));
            if ($input['item_label'] === '') $errors['item_label'] = 'Item label is required.';
            if ($input['student_id'] <= 0) $errors['student_id'] = 'Student is required.';
            if ($input['score'] < 0) $errors['score'] = 'Score is required.';
            if (empty($errors)) {
                $this->grades->create([
                    'course_id' => (int)$course['id'],
                    'student_id' => $input['student_id'],
                    'item_type' => 'manual',
                    'item_label' => $input['item_label'],
                    'score' => $input['score'],
                    'max_score' => $input['max_score'],
                    'feedback' => $input['feedback'],
                    'graded_by' => (int)$user['id'],
                ]);
                $this->session->setFlash('notice', 'Grade recorded.');
                return $response->withHeader('Location', '/courses/' . $course['id'] . '/grades/new')->withStatus(302);
            }
        }
        return $this->view->render($response, 'grade_form.php', [
            'course' => $course,
            'roster' => $roster,
            'input' => $input,
            'errors' => $errors,
            'csrf' => $this->csrf->token(),
            'user' => $user,
        ]);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->auth->currentUser();
        $grade = $this->grades->find((int)$args['id']);
        if (!$grade) {
            return $this->view->render($response->withStatus(404), 'error.php', ['error' => 'Grade not found.', 'status' => 404]);
        }
        $body = (array)$request->getParsedBody();
        $score = (float)($body['score'] ?? -1);
        $feedback = trim((string)($body['feedback'] ?? ''));
        if ($score < 0) {
            $this->session->setFlash('error', 'Score is required.');
        } else {
            $this->grades->update((int)$grade['id'], $score, $feedback);
            $this->session->setFlash('notice', 'Grade updated.');
        }
        return $response->withHeader('Location', '/grades')->withStatus(302);
    }
}
