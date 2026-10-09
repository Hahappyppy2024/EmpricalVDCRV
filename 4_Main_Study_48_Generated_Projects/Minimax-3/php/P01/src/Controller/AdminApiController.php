<?php
declare(strict_types=1);

namespace LMS\Controller;

use LMS\Auth\AuthService;
use LMS\Http\Csrf;
use LMS\Http\Session;
use LMS\Http\View;
use LMS\Repository\AuditRepository;
use LMS\Repository\CourseRepository;
use LMS\Repository\EnrollmentRepository;
use LMS\Repository\SettingsRepository;
use LMS\Repository\UserRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AdminApiController
{
    public function __construct(
        private View $view,
        private UserRepository $users,
        private CourseRepository $courses,
        private EnrollmentRepository $enrollments,
        private AuditRepository $audit,
        private SettingsRepository $settings,
        private AuthService $auth,
        private Session $session,
        private Csrf $csrf
    ) {}

    public function dashboard(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->view->render($response, 'admin_dashboard.php', [
            'userCount' => count($this->users->listAll()),
            'courseCount' => count($this->courses->search([], true)),
            'audit' => $this->audit->recent(10),
            'user' => $this->auth->currentUser(),
        ]);
    }

    public function users(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->view->render($response, 'admin_users.php', [
            'users' => $this->users->listAll(),
            'csrf' => $this->csrf->token(),
            'user' => $this->auth->currentUser(),
        ]);
    }

    public function setRole(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $body = (array)$request->getParsedBody();
        $role = (string)($body['role'] ?? '');
        if (in_array($role, ['student', 'instructor', 'admin'], true)) {
            $this->users->updateRole((int)$args['id'], $role);
            $user = $this->auth->currentUser();
            $this->audit->record((int)$user['id'], 'admin.user.role', 'user', (int)$args['id'], ['role' => $role]);
            $this->session->setFlash('notice', 'Role updated.');
        } else {
            $this->session->setFlash('error', 'Invalid role.');
        }
        return $response->withHeader('Location', '/admin/users')->withStatus(302);
    }

    public function setStatus(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $body = (array)$request->getParsedBody();
        $status = (string)($body['status'] ?? '');
        if (in_array($status, ['active', 'suspended', 'deleted'], true)) {
            $this->users->updateStatus((int)$args['id'], $status);
            $user = $this->auth->currentUser();
            $this->audit->record((int)$user['id'], 'admin.user.status', 'user', (int)$args['id'], ['status' => $status]);
            $this->session->setFlash('notice', 'Status updated.');
        }
        return $response->withHeader('Location', '/admin/users')->withStatus(302);
    }

    public function courses(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->view->render($response, 'admin_courses.php', [
            'courses' => $this->courses->search([], true),
            'csrf' => $this->csrf->token(),
            'user' => $this->auth->currentUser(),
        ]);
    }

    public function createCourse(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $errors = [];
        $input = ['code' => '', 'title' => '', 'description' => '', 'category' => 'General', 'semester' => '2026-Fall', 'instructor_id' => '', 'visibility' => 'open'];
        if (strtoupper($request->getMethod()) === 'POST') {
            $body = (array)$request->getParsedBody();
            $input = array_merge($input, array_map(static fn($v) => is_string($v) ? trim($v) : $v, $body));
            if ($input['code'] === '' || !preg_match('/^[A-Z]{2,4}\d{2,4}$/', $input['code'])) {
                $errors['code'] = 'Code like CS101 required.';
            }
            if ($input['title'] === '') $errors['title'] = 'Title required.';
            if ((int)$input['instructor_id'] <= 0) $errors['instructor_id'] = 'Instructor required.';
            if (!in_array($input['visibility'], ['open', 'closed', 'hidden'], true)) {
                $errors['visibility'] = 'Invalid visibility.';
            }
            if (empty($errors)) {
                $id = $this->courses->create([
                    'code' => $input['code'],
                    'title' => $input['title'],
                    'description' => $input['description'],
                    'category' => $input['category'],
                    'semester' => $input['semester'],
                    'instructor_id' => (int)$input['instructor_id'],
                    'visibility' => $input['visibility'],
                ]);
                $user = $this->auth->currentUser();
                $this->audit->record((int)$user['id'], 'admin.course.create', 'course', $id, ['code' => $input['code']]);
                $this->session->setFlash('notice', 'Course created.');
                return $response->withHeader('Location', '/admin/courses')->withStatus(302);
            }
        }
        return $this->view->render($response, 'admin_course_form.php', [
            'input' => $input,
            'errors' => $errors,
            'instructors' => $this->users->listByRole('instructor'),
            'csrf' => $this->csrf->token(),
            'user' => $this->auth->currentUser(),
        ]);
    }

    public function setVisibility(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $body = (array)$request->getParsedBody();
        $vis = (string)($body['visibility'] ?? '');
        $course = $this->courses->find((int)$args['id']);
        if ($course && in_array($vis, ['open', 'closed', 'hidden'], true)) {
            $this->courses->update((int)$course['id'], [
                'title' => $course['title'],
                'description' => $course['description'],
                'category' => $course['category'],
                'semester' => $course['semester'],
                'visibility' => $vis,
            ]);
            $user = $this->auth->currentUser();
            $this->audit->record((int)$user['id'], 'admin.course.visibility', 'course', (int)$course['id'], ['visibility' => $vis]);
            $this->session->setFlash('notice', 'Visibility updated.');
        }
        return $response->withHeader('Location', '/admin/courses')->withStatus(302);
    }

    public function settings(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->view->render($response, 'admin_settings.php', [
            'settings' => $this->settings->all(),
            'user' => $this->auth->currentUser(),
        ]);
    }

    public function settingsEdit(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (strtoupper($request->getMethod()) === 'POST') {
            $body = (array)$request->getParsedBody();
            $key = (string)($body['key'] ?? '');
            $value = (string)($body['value'] ?? '');
            if (preg_match('/^[a-z_]{2,40}$/', $key)) {
                $this->settings->set($key, $value);
                $user = $this->auth->currentUser();
                $this->audit->record((int)$user['id'], 'admin.settings.update', 'setting', null, ['key' => $key, 'value' => $value]);
                $this->session->setFlash('notice', 'Setting saved.');
            } else {
                $this->session->setFlash('error', 'Invalid setting key.');
            }
            return $response->withHeader('Location', '/admin/settings')->withStatus(302);
        }
        return $this->view->render($response, 'admin_settings_edit.php', [
            'settings' => $this->settings->all(),
            'csrf' => $this->csrf->token(),
            'user' => $this->auth->currentUser(),
        ]);
    }

    public function audit(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->view->render($response, 'admin_audit.php', [
            'rows' => $this->audit->recent(200),
            'user' => $this->auth->currentUser(),
        ]);
    }
}
