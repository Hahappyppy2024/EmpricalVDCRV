<?php
declare(strict_types=1);

namespace LMS\Controller;

use LMS\Auth\AuthService;
use LMS\Http\Csrf;
use LMS\Http\Session;
use LMS\Http\View;
use LMS\Repository\AnnouncementRepository;
use LMS\Repository\CourseRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AnnouncementController
{
    public function __construct(
        private View $view,
        private CourseRepository $courses,
        private AnnouncementRepository $announcements,
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
        $rows = $this->announcements->listForCourse((int)$course['id']);
        return $this->view->render($response, 'announcements.php', [
            'course' => $course,
            'rows' => $rows,
            'user' => $user,
            'csrf' => $this->csrf->token(),
            'canPost' => $this->canPost($user, (int)$course['id']),
            'flash' => ['notice' => $this->session->takeFlash('notice'), 'error' => $this->session->takeFlash('error')],
        ]);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->auth->currentUser();
        $course = $this->courses->find((int)$args['id']);
        if (!$course || !$this->canPost($user, (int)$course['id'])) {
            return $this->view->render($response->withStatus(403), 'error.php', ['error' => 'Forbidden.', 'status' => 403]);
        }
        $errors = [];
        $input = ['title' => '', 'body' => ''];
        if (strtoupper($request->getMethod()) === 'POST') {
            $body = (array)$request->getParsedBody();
            $input['title'] = trim((string)($body['title'] ?? ''));
            $input['body'] = trim((string)($body['body'] ?? ''));
            if ($input['title'] === '') $errors['title'] = 'Title is required.';
            if ($input['body'] === '') $errors['body'] = 'Body is required.';
            if (empty($errors)) {
                $this->announcements->create((int)$course['id'], (int)$user['id'], $input['title'], $input['body']);
                $this->session->setFlash('notice', 'Announcement posted.');
                return $response->withHeader('Location', '/courses/' . $course['id'] . '/announcements')->withStatus(302);
            }
        }
        return $this->view->render($response, 'announcement_form.php', [
            'course' => $course,
            'input' => $input,
            'errors' => $errors,
            'csrf' => $this->csrf->token(),
            'user' => $user,
        ]);
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->auth->currentUser();
        $ann = $this->announcements->find((int)$args['id']);
        if ($ann && $user && $this->canPost($user, (int)$ann['course_id'])) {
            $this->announcements->delete((int)$ann['id']);
            $this->session->setFlash('notice', 'Announcement removed.');
            return $response->withHeader('Location', '/courses/' . $ann['course_id'] . '/announcements')->withStatus(302);
        }
        return $this->view->render($response->withStatus(403), 'error.php', ['error' => 'Forbidden.', 'status' => 403]);
    }

    private function canPost(?array $user, int $courseId): bool
    {
        if (!$user) return false;
        if ($user['role'] === 'admin') return true;
        $course = $this->courses->find($courseId);
        return $course && (int)$course['instructor_id'] === (int)$user['id'];
    }
}
