<?php
declare(strict_types=1);

namespace LMS\Controller;

use LMS\Auth\AuthService;
use LMS\Http\Csrf;
use LMS\Http\Session;
use LMS\Http\View;
use LMS\Repository\CourseRepository;
use LMS\Repository\EnrollmentRepository;
use LMS\Repository\MaterialRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Stream;

final class CourseMaterialController
{
    public function __construct(
        private View $view,
        private CourseRepository $courses,
        private MaterialRepository $materials,
        private EnrollmentRepository $enrollments,
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
        $rows = $this->materials->listForCourse((int)$course['id']);
        return $this->view->render($response, 'course_materials.php', [
            'course' => $course,
            'rows' => $rows,
            'user' => $user,
            'csrf' => $this->csrf->token(),
            'canUpload' => $this->canUpload($user, (int)$course['id']),
            'flash' => ['notice' => $this->session->takeFlash('notice'), 'error' => $this->session->takeFlash('error')],
        ]);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->auth->currentUser();
        $course = $this->courses->find((int)$args['id']);
        if (!$course) {
            return $this->view->render($response->withStatus(404), 'error.php', ['error' => 'Course not found.', 'status' => 404]);
        }
        if (!$this->canUpload($user, (int)$course['id'])) {
            return $this->view->render($response->withStatus(403), 'error.php', ['error' => 'Forbidden.', 'status' => 403]);
        }
        $errors = [];
        if (strtoupper($request->getMethod()) === 'POST') {
            $body = (array)$request->getParsedBody();
            $title = trim((string)($body['title'] ?? ''));
            $description = trim((string)($body['description'] ?? ''));
            if ($title === '') {
                $errors['title'] = 'Title is required.';
            }
            $uploaded = $request->getUploadedFiles()['file'] ?? null;
            if (!$uploaded || $uploaded->getError() !== UPLOAD_ERR_OK) {
                $errors['file'] = 'A file is required.';
            } else {
                $size = (int)$uploaded->getSize();
                $max = (int)($this->config['storage']['uploads_max'] ?? 10485760);
                if ($size <= 0 || $size > $max) {
                    $errors['file'] = 'Invalid file size.';
                }
            }
            if (empty($errors)) {
                $dir = $this->uploadDir();
                $ext = pathinfo($uploaded->getClientFilename() ?? 'file', PATHINFO_EXTENSION);
                $stored = bin2hex(random_bytes(8)) . ($ext ? '.' . preg_replace('/[^a-zA-Z0-9]/', '', $ext) : '');
                $uploaded->moveTo($dir . DIRECTORY_SEPARATOR . $stored);
                $this->materials->create([
                    'course_id' => (int)$course['id'],
                    'title' => $title,
                    'description' => $description,
                    'filename' => $stored,
                    'original_name' => $uploaded->getClientFilename(),
                    'mime' => $uploaded->getClientMediaType() ?? 'application/octet-stream',
                    'size' => $size,
                    'uploaded_by' => (int)$user['id'],
                ]);
                $this->session->setFlash('notice', 'Material uploaded.');
                return $response->withHeader('Location', '/courses/' . $course['id'] . '/materials')->withStatus(302);
            }
        }
        return $this->view->render($response, 'material_form.php', [
            'course' => $course,
            'errors' => $errors,
            'csrf' => $this->csrf->token(),
            'user' => $user,
        ]);
    }

    public function download(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $material = $this->materials->find((int)$args['id']);
        if (!$material) {
            return $this->view->render($response->withStatus(404), 'error.php', ['error' => 'Material not found.', 'status' => 404]);
        }
        $user = $this->auth->currentUser();
        $course = $this->courses->find((int)$material['course_id']);
        $allowed = $user && (
            in_array($user['role'], ['admin'], true)
            || $user['id'] == $course['instructor_id']
            || $this->enrollments->isEnrolled((int)$user['id'], (int)$course['id'])
        );
        if (!$allowed) {
            return $this->view->render($response->withStatus(403), 'error.php', ['error' => 'Forbidden.', 'status' => 403]);
        }
        $path = $this->uploadDir() . DIRECTORY_SEPARATOR . $material['filename'];
        if (!is_file($path)) {
            return $this->view->render($response->withStatus(404), 'error.php', ['error' => 'File missing on storage.', 'status' => 404]);
        }
        $response = $response->withHeader('Content-Type', $material['mime'] ?: 'application/octet-stream');
        $response = $response->withHeader('Content-Disposition', 'attachment; filename="' . $material['original_name'] . '"');
        $response = $response->withHeader('Content-Length', (string)filesize($path));
        $response->getBody()->write((string)file_get_contents($path));
        return $response;
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $material = $this->materials->find((int)$args['id']);
        if ($material) {
            $user = $this->auth->currentUser();
            $course = $this->courses->find((int)$material['course_id']);
            if ($user && (int)$course['instructor_id'] === (int)$user['id']) {
                $path = $this->uploadDir() . DIRECTORY_SEPARATOR . $material['filename'];
                if (is_file($path)) {
                    @unlink($path);
                }
                $this->materials->delete((int)$material['id']);
                $this->session->setFlash('notice', 'Material removed.');
                return $response->withHeader('Location', '/courses/' . $material['course_id'] . '/materials')->withStatus(302);
            }
        }
        return $this->view->render($response->withStatus(403), 'error.php', ['error' => 'Forbidden.', 'status' => 403]);
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

    private function canUpload(?array $user, int $courseId): bool
    {
        if (!$user) {
            return false;
        }
        if ($user['role'] === 'admin') {
            return true;
        }
        $course = $this->courses->find($courseId);
        return $course && (int)$course['instructor_id'] === (int)$user['id'];
    }
}
