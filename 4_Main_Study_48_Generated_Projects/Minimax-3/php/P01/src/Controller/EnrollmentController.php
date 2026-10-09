<?php
declare(strict_types=1);

namespace LMS\Controller;

use LMS\Auth\AuthService;
use LMS\Http\Csrf;
use LMS\Http\Session;
use LMS\Http\View;
use LMS\Repository\CourseRepository;
use LMS\Repository\EnrollmentRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class EnrollmentController
{
    public function __construct(
        private View $view,
        private CourseRepository $courses,
        private EnrollmentRepository $enrollments,
        private AuthService $auth,
        private Session $session,
        private Csrf $csrf
    ) {}

    public function enroll(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->auth->currentUser();
        $course = $this->courses->find((int)$args['id']);
        if (!$course) {
            $this->session->setFlash('error', 'Course not found.');
            return $response->withHeader('Location', '/courses')->withStatus(302);
        }
        if ($course['visibility'] === 'closed' && !in_array($user['role'], ['instructor', 'admin'], true)) {
            $this->session->setFlash('error', 'This course is closed for enrollment.');
            return $response->withHeader('Location', '/courses/' . $course['id'])->withStatus(302);
        }
        if (strtoupper($request->getMethod()) === 'POST') {
            $result = $this->enrollments->enroll((int)$user['id'], (int)$course['id']);
            $this->session->setFlash(
                $result['ok'] ? 'notice' : 'error',
                $result['ok'] ? 'Enrolled in ' . $course['code'] : ($result['error'] ?? 'Could not enroll.')
            );
            return $response->withHeader('Location', '/my/courses')->withStatus(302);
        }
        return $this->view->render($response, 'enroll.php', [
            'course' => $course,
            'user' => $user,
            'already' => $this->enrollments->isEnrolled((int)$user['id'], (int)$course['id']),
            'csrf' => $this->csrf->token(),
        ]);
    }

    public function drop(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->auth->currentUser();
        $this->enrollments->drop((int)$user['id'], (int)$args['id']);
        $this->session->setFlash('notice', 'Dropped course.');
        return $response->withHeader('Location', '/my/courses')->withStatus(302);
    }

    public function mine(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $this->auth->currentUser();
        $rows = $this->enrollments->forUser((int)$user['id']);
        return $this->view->render($response, 'enrollments_mine.php', [
            'rows' => $rows,
            'user' => $user,
            'csrf' => $this->csrf->token(),
            'flash' => ['notice' => $this->session->takeFlash('notice'), 'error' => $this->session->takeFlash('error')],
        ]);
    }

    public function roster(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $course = $this->courses->find((int)$args['id']);
        if (!$course) {
            return $this->view->render($response->withStatus(404), 'error.php', ['error' => 'Course not found.', 'status' => 404]);
        }
        $rows = $this->enrollments->roster((int)$course['id']);
        return $this->view->render($response, 'roster.php', [
            'course' => $course,
            'rows' => $rows,
            'user' => $this->auth->currentUser(),
        ]);
    }
}
