<?php
declare(strict_types=1);

namespace LMS\Controller;

use LMS\Auth\AuthService;
use LMS\Http\Csrf;
use LMS\Http\View;
use LMS\Repository\CourseRepository;
use LMS\Repository\EnrollmentRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Frontend API integration (LMS-13).
 *
 * Single page that exercises four explicit response states via the
 * browser UI: loading, validation, empty, error. The browser-side
 * fetch wrapper is in /assets/app.js (window.lmsApi).
 */
final class FrontendApiController
{
    public function __construct(
        private View $view,
        private CourseRepository $courses,
        private EnrollmentRepository $enrollments,
        private AuthService $auth,
        private Csrf $csrf
    ) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->view->render($response, 'frontend_api.php', [
            'user' => $this->auth->currentUser(),
            'categories' => $this->courses->listCategories(),
            'semesters' => $this->courses->listSemesters(),
            'instructors' => $this->courses->listInstructors(),
            'myCourses' => $this->enrollments->forUser((int)$this->auth->currentUser()['id']),
            'csrf' => $this->csrf->token(),
        ]);
    }
}
