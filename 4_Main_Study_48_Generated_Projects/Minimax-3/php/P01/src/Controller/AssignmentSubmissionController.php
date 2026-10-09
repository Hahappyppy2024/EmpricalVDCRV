<?php
declare(strict_types=1);

namespace LMS\Controller;

use LMS\Auth\AuthService;
use LMS\Http\Csrf;
use LMS\Http\Session;
use LMS\Http\View;
use LMS\Repository\AssignmentRepository;
use LMS\Repository\CourseRepository;
use LMS\Repository\EnrollmentRepository;
use LMS\Repository\GradeRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AssignmentSubmissionController
{
    public function __construct(
        private View $view,
        private AssignmentRepository $assignments,
        private CourseRepository $courses,
        private EnrollmentRepository $enrollments,
        private GradeRepository $grades,
        private AuthService $auth,
        private Session $session,
        private Csrf $csrf,
        private array $config
    ) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->auth->currentUser();
        $course = $this->courses->find((int)$args['id']);
        if (!$course) {
            return $this->view->render($response->withStatus(404), 'error.php', ['error' => 'Course not found.', 'status' => 404]);
        }
        if ($user['role'] === 'student') {
            $rows = $this->assignments->listForCourseWithSubmission((int)$course['id'], (int)$user['id']);
        } else {
            $rows = $this->assignments->listForCourse((int)$course['id']);
        }
        return $this->view->render($response, 'assignments.php', [
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
        if (!$course || ($user['role'] !== 'instructor' && $user['role'] !== 'admin')) {
            return $this->view->render($response->withStatus(403), 'error.php', ['error' => 'Forbidden.', 'status' => 403]);
        }
        $errors = [];
        $input = ['title' => '', 'instructions' => '', 'due_at' => date('Y-m-d\TH:i', strtotime('+7 days')), 'max_score' => 100];
        if (strtoupper($request->getMethod()) === 'POST') {
            $body = (array)$request->getParsedBody();
            $input['title'] = trim((string)($body['title'] ?? ''));
            $input['instructions'] = trim((string)($body['instructions'] ?? ''));
            $input['due_at'] = str_replace('T', ' ', (string)($body['due_at'] ?? '')) . ':00';
            $input['max_score'] = (float)($body['max_score'] ?? 100);
            if ($input['title'] === '') $errors['title'] = 'Title is required.';
            if (empty($input['due_at'])) $errors['due_at'] = 'Due date is required.';
            if (empty($errors)) {
                $id = $this->assignments->create([
                    'course_id' => (int)$course['id'],
                    'title' => $input['title'],
                    'instructions' => $input['instructions'],
                    'due_at' => $input['due_at'],
                    'max_score' => $input['max_score'],
                    'created_by' => (int)$user['id'],
                ]);
                $this->session->setFlash('notice', 'Assignment created.');
                return $response->withHeader('Location', '/courses/' . $course['id'] . '/assignments')->withStatus(302);
            }
        }
        return $this->view->render($response, 'assignment_form.php', [
            'course' => $course,
            'input' => $input,
            'errors' => $errors,
            'csrf' => $this->csrf->token(),
            'user' => $user,
        ]);
    }

    public function submitForm(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->auth->currentUser();
        $assignment = $this->assignments->find((int)$args['id']);
        if (!$assignment) {
            return $this->view->render($response->withStatus(404), 'error.php', ['error' => 'Assignment not found.', 'status' => 404]);
        }
        if (!$this->enrollments->isEnrolled((int)$user['id'], (int)$assignment['course_id'])) {
            return $this->view->render($response->withStatus(403), 'error.php', ['error' => 'Not enrolled.', 'status' => 403]);
        }
        return $this->view->render($response, 'submission_form.php', [
            'assignment' => $assignment,
            'existing' => $this->assignments->findSubmission((int)$assignment['id'], (int)$user['id']),
            'csrf' => $this->csrf->token(),
            'user' => $user,
        ]);
    }

    public function submit(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->auth->currentUser();
        $assignment = $this->assignments->find((int)$args['id']);
        if (!$assignment) {
            return $this->view->render($response->withStatus(404), 'error.php', ['error' => 'Assignment not found.', 'status' => 404]);
        }
        if (!$this->enrollments->isEnrolled((int)$user['id'], (int)$assignment['course_id'])) {
            return $this->view->render($response->withStatus(403), 'error.php', ['error' => 'Not enrolled.', 'status' => 403]);
        }
        $uploaded = $request->getUploadedFiles()['file'] ?? null;
        $comment = trim((string)((array)$request->getParsedBody())['comment'] ?? '');
        if (!$uploaded || $uploaded->getError() !== UPLOAD_ERR_OK) {
            $this->session->setFlash('error', 'File upload failed.');
            return $response->withHeader('Location', '/assignments/' . $assignment['id'] . '/submit')->withStatus(302);
        }
        $dir = $this->uploadDir();
        $ext = pathinfo($uploaded->getClientFilename() ?? '', PATHINFO_EXTENSION);
        $stored = bin2hex(random_bytes(8)) . ($ext ? '.' . preg_replace('/[^a-zA-Z0-9]/', '', $ext) : '');
        $uploaded->moveTo($dir . DIRECTORY_SEPARATOR . $stored);
        $result = $this->assignments->submit((int)$assignment['id'], (int)$user['id'], [
            'stored_name' => $stored,
            'original_name' => $uploaded->getClientFilename(),
            'mime' => $uploaded->getClientMediaType() ?? 'application/octet-stream',
            'size' => (int)$uploaded->getSize(),
        ], $comment);
        if (!$result['ok']) {
            unlink($dir . DIRECTORY_SEPARATOR . $stored);
            $this->session->setFlash('error', $result['error']);
            return $response->withHeader('Location', '/assignments/' . $assignment['id'] . '/submit')->withStatus(302);
        }
        $this->session->setFlash('notice', $result['late'] ? 'Submitted (late).' : 'Submission uploaded.');
        return $response->withHeader('Location', '/courses/' . $assignment['course_id'] . '/assignments')->withStatus(302);
    }

    public function submissions(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->auth->currentUser();
        $assignment = $this->assignments->find((int)$args['id']);
        if (!$assignment) {
            return $this->view->render($response->withStatus(404), 'error.php', ['error' => 'Assignment not found.', 'status' => 404]);
        }
        $rows = $this->assignments->listSubmissions((int)$assignment['id']);
        return $this->view->render($response, 'submissions_list.php', [
            'assignment' => $assignment,
            'rows' => $rows,
            'csrf' => $this->csrf->token(),
            'user' => $user,
        ]);
    }

    public function grade(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->auth->currentUser();
        $submission = $this->assignments->findSubmissionById((int)$args['id']);
        if (!$submission) {
            return $this->view->render($response->withStatus(404), 'error.php', ['error' => 'Submission not found.', 'status' => 404]);
        }
        $body = (array)$request->getParsedBody();
        $score = (float)($body['score'] ?? -1);
        $feedback = trim((string)($body['feedback'] ?? ''));
        if ($score < 0) {
            $this->session->setFlash('error', 'Score is required.');
            return $response->withHeader('Location', '/assignments/' . $submission['assignment_id'] . '/submissions')->withStatus(302);
        }
        $this->assignments->grade((int)$submission['id'], $score, $feedback, (int)$user['id']);
        $this->grades->create([
            'course_id' => (int)$submission['course_id'],
            'student_id' => (int)$submission['student_id'],
            'item_type' => 'assignment',
            'item_id' => (int)$submission['assignment_id'],
            'item_label' => $submission['assignment_title'],
            'score' => $score,
            'max_score' => 100,
            'feedback' => $feedback,
            'graded_by' => (int)$user['id'],
        ]);
        $this->session->setFlash('notice', 'Graded.');
        return $response->withHeader('Location', '/assignments/' . $submission['assignment_id'] . '/submissions')->withStatus(302);
    }

    private function uploadDir(): string
    {
        $dir = $this->config['storage']['uploads'];
        if (!str_starts_with($dir, DIRECTORY_SEPARATOR) && !preg_match('#^[A-Za-z]:[\\\\/]#', $dir)) {
            $dir = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . $dir;
        }
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        return $dir;
    }
}
