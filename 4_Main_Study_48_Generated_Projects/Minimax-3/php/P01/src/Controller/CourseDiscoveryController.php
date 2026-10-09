<?php
declare(strict_types=1);

namespace LMS\Controller;

use LMS\Auth\AuthService;
use LMS\Http\Csrf;
use LMS\Http\Session;
use LMS\Http\View;
use LMS\Repository\CourseRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class CourseDiscoveryController
{
    public function __construct(
        private View $view,
        private CourseRepository $courses,
        private AuthService $auth,
        private Session $session,
        private Csrf $csrf
    ) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $filters = [
            'q'          => trim((string)($params['q'] ?? '')),
            'category'   => trim((string)($params['category'] ?? '')),
            'instructor' => trim((string)($params['instructor'] ?? '')),
            'semester'   => trim((string)($params['semester'] ?? '')),
        ];
        $user = $this->auth->currentUser();
        $results = $this->courses->search($filters, $user && $user['role'] === 'admin');
        return $this->view->render($response, 'course_discovery.php', [
            'user' => $user,
            'results' => $results,
            'filters' => $filters,
            'categories' => $this->courses->listCategories(),
            'semesters'  => $this->courses->listSemesters(),
            'instructors'=> $this->courses->listInstructors(),
            'csrf' => $this->csrf->token(),
            'flash' => ['notice' => $this->session->takeFlash('notice')],
        ]);
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $course = $this->courses->find((int)$args['id']);
        if (!$course || $course['visibility'] === 'hidden') {
            $user = $this->auth->currentUser();
            if (!$user || ($user['role'] !== 'admin' && $user['id'] != $course['instructor_id'])) {
                return $this->view->render($response->withStatus(404), 'error.php', [
                    'error' => 'Course not found.',
                    'status' => 404,
                ]);
            }
        }
        return $this->view->render($response, 'course_detail.php', [
            'course' => $course,
            'user' => $this->auth->currentUser(),
            'csrf' => $this->csrf->token(),
        ]);
    }
}
