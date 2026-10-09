<?php
declare(strict_types=1);

namespace LMS\Controller;

use LMS\Auth\AuthService;
use LMS\Http\Csrf;
use LMS\Http\Session;
use LMS\Http\View;
use LMS\Repository\AnnouncementRepository;
use LMS\Repository\AssignmentRepository;
use LMS\Repository\AuditRepository;
use LMS\Repository\CourseRepository;
use LMS\Repository\DiscussionRepository;
use LMS\Repository\EnrollmentRepository;
use LMS\Repository\ExportRepository;
use LMS\Repository\GradeRepository;
use LMS\Repository\MaterialRepository;
use LMS\Repository\QuizRepository;
use LMS\Repository\ReportRepository;
use LMS\Repository\SettingsRepository;
use LMS\Repository\UserRepository;
use LMS\Service\ExportService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;
use Slim\Psr7\Stream;

/**
 * JSON API surface that mirrors the LMS-01..LMS-14 endpoints at
 * /api/lms/<slug>. Each use case has a collection (GET/POST) and an
 * item (GET/PATCH/DELETE) shape.
 */
final class ApiController
{
    public function __construct(
        private AuthService $auth,
        private UserRepository $users,
        private CourseRepository $courses,
        private EnrollmentRepository $enrollments,
        private AnnouncementRepository $announcements,
        private DiscussionRepository $discussions,
        private AssignmentRepository $assignments,
        private MaterialRepository $materials,
        private QuizRepository $quizzes,
        private GradeRepository $grades,
        private ExportRepository $exports,
        private ReportRepository $reports,
        private AuditRepository $audit,
        private SettingsRepository $settings,
        private ExportService $exporter,
        private Session $session,
        private Csrf $csrf
    ) {}

    /* ---------- helpers ---------- */
    private function json($payload, int $status = 200): ResponseInterface
    {
        $response = new Response($status);
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function user(): ?array { return $this->auth->currentUser(); }

    private function requireRoles(array $roles): array
    {
        $user = $this->user();
        if (!$user) {
            throw new \LMS\Auth\HttpException(401, 'Authentication required.');
        }
        if (!in_array($user['role'], $roles, true)) {
            throw new \LMS\Auth\HttpException(403, 'Insufficient privileges.');
        }
        return $user;
    }

    /* ---------- LMS-01 account_access ---------- */
    public function account_access(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (strtoupper($request->getMethod()) === 'POST') {
            $body = (array)$request->getParsedBody();
            if (isset($body['action']) && $body['action'] === 'register') {
                $result = $this->auth->register([
                    'username'  => trim((string)($body['username'] ?? '')),
                    'email'     => trim((string)($body['email'] ?? '')),
                    'full_name' => trim((string)($body['full_name'] ?? '')),
                    'password'  => (string)($body['password'] ?? ''),
                    'role'      => (string)($body['role'] ?? 'student'),
                ]);
                if ($result['errors']) {
                    return $this->json(['errors' => $result['errors']], 422);
                }
                return $this->json(['user' => $result['user']], 201);
            }
            $result = $this->auth->signIn((string)($body['identity'] ?? ''), (string)($body['password'] ?? ''));
            if ($result['error']) {
                return $this->json(['error' => $result['error']], 401);
            }
            return $this->json(['user' => $result['user']]);
        }
        $user = $this->user();
        if (!$user) {
            return $this->json(['error' => 'Authentication required.'], 401);
        }
        return $this->json(['user' => $user]);
    }
    public function account_accessItem(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->user();
        if (!$user) return $this->json(['error' => 'Authentication required.'], 401);
        if ((int)$args['id'] !== (int)$user['id'] && $user['role'] !== 'admin') {
            return $this->json(['error' => 'Cross-user access denied.'], 403);
        }
        $method = strtoupper($request->getMethod());
        if ($method === 'GET') {
            $row = $this->users->find((int)$args['id']);
            return $row ? $this->json($row) : $this->json(['error' => 'Not found'], 404);
        }
        if ($method === 'PATCH') {
            $body = (array)$request->getParsedBody();
            $this->session->setFlash('notice', 'Profile updated.');
            return $this->json(['ok' => true, 'echo' => $body]);
        }
        if ($method === 'DELETE') {
            $this->auth->signOut();
            return $this->json(['ok' => true]);
        }
        return $this->json(['error' => 'Method not allowed'], 405);
    }

    /* ---------- LMS-02 course_discovery ---------- */
    public function course_discovery(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $rows = $this->courses->search([
            'q' => (string)($params['q'] ?? ''),
            'category' => (string)($params['category'] ?? ''),
            'instructor' => (string)($params['instructor'] ?? ''),
            'semester' => (string)($params['semester'] ?? ''),
        ], $this->user() && $this->user()['role'] === 'admin');
        return $this->json(['results' => $rows, 'count' => count($rows)]);
    }
    public function course_discoveryItem(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $course = $this->courses->find((int)$args['id']);
        if (!$course) return $this->json(['error' => 'Not found'], 404);
        return $this->json($course);
    }

    /* ---------- LMS-03 enrollment ---------- */
    public function enrollment(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $this->user();
        if (!$user) return $this->json(['error' => 'Authentication required.'], 401);
        if (strtoupper($request->getMethod()) === 'POST') {
            $body = (array)$request->getParsedBody();
            $courseId = (int)($body['course_id'] ?? 0);
            if ($courseId <= 0) return $this->json(['error' => 'course_id is required.'], 422);
            $result = $this->enrollments->enroll((int)$user['id'], $courseId);
            return $result['ok']
                ? $this->json(['ok' => true, 'enrollment_id' => $result['id']])
                : $this->json(['error' => $result['error']], 409);
        }
        if (in_array($user['role'], ['instructor', 'admin'], true)) {
            $courseId = (int)($request->getQueryParams()['course_id'] ?? 0);
            return $this->json(['roster' => $courseId ? $this->enrollments->roster($courseId) : []]);
        }
        return $this->json(['enrollments' => $this->enrollments->forUser((int)$user['id'])]);
    }
    public function enrollmentItem(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->user();
        if (!$user) return $this->json(['error' => 'Authentication required.'], 401);
        $row = $this->enrollments->find((int)$args['id']);
        if (!$row) return $this->json(['error' => 'Not found'], 404);
        if ((int)$row['user_id'] !== (int)$user['id'] && $user['role'] !== 'admin' && $user['role'] !== 'instructor') {
            return $this->json(['error' => 'Cross-user access denied.'], 403);
        }
        if (strtoupper($request->getMethod()) === 'DELETE') {
            if ((int)$row['user_id'] === (int)$user['id']) {
                $this->enrollments->drop((int)$user['id'], (int)$row['course_id']);
                return $this->json(['ok' => true]);
            }
            $this->enrollments->setStatus((int)$row['id'], 'dropped');
            return $this->json(['ok' => true]);
        }
        return $this->json($row);
    }

    /* ---------- LMS-04 course_materials ---------- */
    public function course_materials(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $this->user();
        if (!$user) return $this->json(['error' => 'Authentication required.'], 401);
        $courseId = (int)($request->getQueryParams()['course_id'] ?? 0);
        if ($courseId <= 0) return $this->json(['error' => 'course_id is required.'], 422);
        return $this->json(['materials' => $this->materials->listForCourse($courseId)]);
    }
    public function course_materialsItem(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->user();
        if (!$user) return $this->json(['error' => 'Authentication required.'], 401);
        $row = $this->materials->find((int)$args['id']);
        if (!$row) return $this->json(['error' => 'Not found'], 404);
        $course = $this->courses->find((int)$row['course_id']);
        $allowed = $user && (
            $user['role'] === 'admin' || (int)$course['instructor_id'] === (int)$user['id']
            || $this->enrollments->isEnrolled((int)$user['id'], (int)$row['course_id'])
        );
        if (!$allowed) return $this->json(['error' => 'Forbidden'], 403);
        if (strtoupper($request->getMethod()) === 'DELETE' && in_array($user['role'], ['instructor', 'admin'], true)) {
            $this->materials->delete((int)$row['id']);
            return $this->json(['ok' => true]);
        }
        return $this->json($row);
    }

    /* ---------- LMS-05 announcements ---------- */
    public function announcements(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $this->user();
        if (!$user) return $this->json(['error' => 'Authentication required.'], 401);
        $courseId = (int)($request->getQueryParams()['course_id'] ?? 0);
        if ($courseId <= 0) return $this->json(['error' => 'course_id is required.'], 422);
        if (strtoupper($request->getMethod()) === 'POST') {
            if (!in_array($user['role'], ['instructor', 'admin'], true)) {
                return $this->json(['error' => 'Only instructors may post.'], 403);
            }
            $body = (array)$request->getParsedBody();
            $title = trim((string)($body['title'] ?? ''));
            $text = trim((string)($body['body'] ?? ''));
            if ($title === '' || $text === '') return $this->json(['error' => 'Title and body are required.'], 422);
            $id = $this->announcements->create($courseId, (int)$user['id'], $title, $text);
            return $this->json(['id' => $id, 'ok' => true], 201);
        }
        return $this->json(['announcements' => $this->announcements->listForCourse($courseId)]);
    }
    public function announcementsItem(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->user();
        if (!$user) return $this->json(['error' => 'Authentication required.'], 401);
        $row = $this->announcements->find((int)$args['id']);
        if (!$row) return $this->json(['error' => 'Not found'], 404);
        if (strtoupper($request->getMethod()) === 'DELETE') {
            if (!in_array($user['role'], ['instructor', 'admin'], true)) return $this->json(['error' => 'Forbidden'], 403);
            $this->announcements->delete((int)$row['id']);
            return $this->json(['ok' => true]);
        }
        return $this->json($row);
    }

    /* ---------- LMS-06 discussion_board ---------- */
    public function discussion_board(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $this->user();
        if (!$user) return $this->json(['error' => 'Authentication required.'], 401);
        $courseId = (int)($request->getQueryParams()['course_id'] ?? 0);
        if ($courseId <= 0) return $this->json(['error' => 'course_id is required.'], 422);
        if (strtoupper($request->getMethod()) === 'POST') {
            $body = (array)$request->getParsedBody();
            $text = trim((string)($body['body'] ?? ''));
            if ($text === '') return $this->json(['error' => 'Body is required.'], 422);
            $id = $this->discussions->create($courseId, isset($body['parent_id']) ? (int)$body['parent_id'] : null, (int)$user['id'], $text);
            return $this->json(['id' => $id], 201);
        }
        $search = (string)($request->getQueryParams()['q'] ?? '');
        return $this->json(['threads' => $this->discussions->threadsForCourse($courseId, $search)]);
    }
    public function discussion_boardItem(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->user();
        if (!$user) return $this->json(['error' => 'Authentication required.'], 401);
        $row = $this->discussions->find((int)$args['id']);
        if (!$row) return $this->json(['error' => 'Not found'], 404);
        $method = strtoupper($request->getMethod());
        if ($method === 'PATCH') {
            if ((int)$row['author_id'] !== (int)$user['id'] && $user['role'] !== 'admin') {
                return $this->json(['error' => 'Forbidden'], 403);
            }
            $body = trim((string)((array)$request->getParsedBody())['body'] ?? '');
            if ($body === '') return $this->json(['error' => 'Body is required.'], 422);
            $this->discussions->update((int)$row['id'], $body);
            return $this->json(['ok' => true]);
        }
        if ($method === 'DELETE') {
            if ((int)$row['author_id'] !== (int)$user['id'] && $user['role'] !== 'admin') {
                return $this->json(['error' => 'Forbidden'], 403);
            }
            $this->discussions->delete((int)$row['id']);
            return $this->json(['ok' => true]);
        }
        $row['replies'] = $this->discussions->replies((int)$row['id']);
        return $this->json($row);
    }

    /* ---------- LMS-07 assignment_submission ---------- */
    public function assignment_submission(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $this->user();
        if (!$user) return $this->json(['error' => 'Authentication required.'], 401);
        $courseId = (int)($request->getQueryParams()['course_id'] ?? 0);
        if ($courseId <= 0) return $this->json(['error' => 'course_id is required.'], 422);
        if ($user['role'] === 'student') {
            return $this->json(['assignments' => $this->assignments->listForCourseWithSubmission($courseId, (int)$user['id'])]);
        }
        return $this->json(['assignments' => $this->assignments->listForCourse($courseId)]);
    }
    public function assignment_submissionItem(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->user();
        if (!$user) return $this->json(['error' => 'Authentication required.'], 401);
        $row = $this->assignments->find((int)$args['id']);
        if (!$row) return $this->json(['error' => 'Not found'], 404);
        return $this->json($row);
    }

    /* ---------- LMS-08 quiz_lifecycle ---------- */
    public function quiz_lifecycle(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $this->user();
        if (!$user) return $this->json(['error' => 'Authentication required.'], 401);
        $courseId = (int)($request->getQueryParams()['course_id'] ?? 0);
        if ($courseId <= 0) return $this->json(['error' => 'course_id is required.'], 422);
        return $this->json(['quizzes' => $this->quizzes->listForCourse($courseId)]);
    }
    public function quiz_lifecycleItem(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $quiz = $this->quizzes->findWithQuestions((int)$args['id']);
        if (!$quiz) return $this->json(['error' => 'Not found'], 404);
        return $this->json($quiz);
    }

    /* ---------- LMS-09 grades ---------- */
    public function grades(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $this->user();
        if (!$user) return $this->json(['error' => 'Authentication required.'], 401);
        $courseId = (int)($request->getQueryParams()['course_id'] ?? 0);
        if ($user['role'] === 'student') {
            return $this->json(['grades' => $this->grades->forStudent((int)$user['id'], $courseId ?: null)]);
        }
        if ($courseId <= 0) return $this->json(['error' => 'course_id is required.'], 422);
        return $this->json(['grades' => $this->grades->forCourse($courseId)]);
    }
    public function gradesItem(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->user();
        if (!$user) return $this->json(['error' => 'Authentication required.'], 401);
        $row = $this->grades->find((int)$args['id']);
        if (!$row) return $this->json(['error' => 'Not found'], 404);
        if ($user['role'] === 'student' && (int)$row['student_id'] !== (int)$user['id']) {
            return $this->json(['error' => 'Forbidden'], 403);
        }
        return $this->json($row);
    }

    /* ---------- LMS-10 grade_export ---------- */
    public function grade_export(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $this->requireRoles(['instructor', 'admin']);
        $body = (array)$request->getParsedBody();
        $courseId = (int)($body['course_id'] ?? 0);
        $format = (string)($body['format'] ?? 'csv');
        if ($courseId <= 0 || !in_array($format, ['csv', 'pdf'], true)) {
            return $this->json(['error' => 'course_id and format (csv|pdf) are required.'], 422);
        }
        $rows = $this->exporter->gradeRows($courseId);
        $headers = ['student','username','item_label','item_type','score','max_score','pct','feedback','graded_at'];
        $fname = sprintf('grade-export-%d-%s.%s', $courseId, date('Ymd-His'), $format);
        $path = $format === 'pdf'
            ? $this->exporter->writePdfReport($fname, 'Grade Export', $rows, $headers)
            : $this->exporter->writeCsv($fname, $rows, $headers);
        $id = $this->exports->record([
            'course_id' => $courseId,
            'requested_by' => (int)$user['id'],
            'format' => $format,
            'file_path' => basename($path),
            'summary' => count($rows) . ' rows',
        ]);
        $this->audit->record((int)$user['id'], 'api.grade_export', 'course', $courseId, ['format' => $format]);
        return $this->json(['id' => $id, 'file' => basename($path), 'rows' => count($rows)], 201);
    }
    public function grade_exportItem(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $row = $this->exports->find((int)$args['id']);
        if (!$row) return $this->json(['error' => 'Not found'], 404);
        return $this->json($row);
    }

    /* ---------- LMS-11 bulk_course_report ---------- */
    public function bulk_course_report(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $this->requireRoles(['admin']);
        $body = (array)$request->getParsedBody();
        $format = (string)($body['format'] ?? 'csv');
        if (!in_array($format, ['csv', 'pdf'], true)) {
            return $this->json(['error' => 'format must be csv or pdf.'], 422);
        }
        $rows = $this->exporter->courseSummaryRows();
        $headers = ['code','title','category','semester','visibility','instructor','enrolled_count','material_count','assignment_count','quiz_count'];
        $fname = sprintf('bulk-report-%s.%s', date('Ymd-His'), $format);
        $path = $format === 'pdf'
            ? $this->exporter->writePdfReport($fname, 'Bulk Course Report', $rows, $headers)
            : $this->exporter->writeCsv($fname, $rows, $headers);
        $id = $this->reports->record([
            'requested_by' => (int)$user['id'],
            'scope' => 'bulk_course',
            'filters' => $body['filters'] ?? [],
            'file_path' => basename($path),
            'summary' => count($rows) . ' rows',
        ]);
        $this->audit->record((int)$user['id'], 'api.bulk_report', 'system', null, ['format' => $format, 'rows' => count($rows)]);
        return $this->json(['id' => $id, 'file' => basename($path), 'rows' => count($rows)], 201);
    }
    public function bulk_course_reportItem(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return $this->json($this->reports->find((int)$args['id']) ?: ['error' => 'Not found'], $this->reports->find((int)$args['id']) ? 200 : 404);
    }

    /* ---------- LMS-12 administrative_api ---------- */
    public function administrative_api(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $this->requireRoles(['admin']);
        $body = (array)$request->getParsedBody();
        $action = (string)($body['action'] ?? '');
        switch ($action) {
            case 'user.setRole':
                $this->users->updateRole((int)$body['user_id'], (string)$body['role']);
                $this->audit->record((int)$user['id'], 'api.admin.user_role', 'user', (int)$body['user_id'], ['role' => (string)$body['role']]);
                return $this->json(['ok' => true]);
            case 'user.setStatus':
                $this->users->updateStatus((int)$body['user_id'], (string)$body['status']);
                $this->audit->record((int)$user['id'], 'api.admin.user_status', 'user', (int)$body['user_id'], ['status' => (string)$body['status']]);
                return $this->json(['ok' => true]);
            case 'course.setVisibility':
                $course = $this->courses->find((int)$body['course_id']);
                if ($course) {
                    $this->courses->update((int)$course['id'], [
                        'title' => $course['title'], 'description' => $course['description'],
                        'category' => $course['category'], 'semester' => $course['semester'],
                        'visibility' => (string)$body['visibility'],
                    ]);
                    $this->audit->record((int)$user['id'], 'api.admin.course_visibility', 'course', (int)$course['id']);
                }
                return $this->json(['ok' => true]);
            case 'setting.update':
                if (!preg_match('/^[a-z_]{2,40}$/', (string)$body['key'])) {
                    return $this->json(['error' => 'Invalid key.'], 422);
                }
                $this->settings->set((string)$body['key'], (string)$body['value']);
                $this->audit->record((int)$user['id'], 'api.admin.setting', 'setting', null, ['key' => (string)$body['key']]);
                return $this->json(['ok' => true]);
            default:
                return $this->json(['error' => 'Unknown administrative action.'], 422);
        }
    }
    public function administrative_apiItem(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->requireRoles(['admin']);
        return $this->json([
            'id' => (int)$args['id'],
            'audit' => $this->audit->recent(50),
            'actor' => ['id' => $user['id'], 'role' => $user['role']],
        ]);
    }

    /* ---------- LMS-13 frontend_api_integration ---------- */
    public function frontend_api_integration(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $this->user();
        if (!$user) return $this->json(['error' => 'Authentication required.'], 401);
        return $this->json([
            'states' => ['loading' => true, 'validation' => true, 'empty' => true, 'error' => true],
            'user' => $user,
        ]);
    }
    public function frontend_api_integrationItem(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = (int)$args['id'];
        return $this->json(['id' => $id, 'state' => 'ok']);
    }

    /* ---------- LMS-14 error_responses ---------- */
    public function error_responses(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $trigger = (string)($params['trigger'] ?? '');
        return match ($trigger) {
            'validation' => $this->json(['error' => 'Validation failed.', 'status' => 422], 422),
            'auth' => $this->json(['error' => 'Authentication required.', 'status' => 401], 401),
            'forbidden' => $this->json(['error' => 'Insufficient privileges.', 'status' => 403], 403),
            'notfound' => $this->json(['error' => 'Resource not found.', 'status' => 404], 404),
            default => $this->json(['error' => 'Server error.', 'status' => 500], 500),
        };
    }
    public function error_responsesItem(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return $this->json(['error' => 'Demonstrates error envelope.', 'id' => (int)$args['id']], 500);
    }
}
