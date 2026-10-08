<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config;
use App\Database;
use App\Http;
use App\Repositories\AssignmentRepository;
use App\Repositories\CourseRepository;
use App\Repositories\GradeRepository;
use App\Repositories\QuizRepository;
use App\Repositories\SystemRepository;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Services\CapabilityService;
use App\Services\ExportService;
use App\Services\FileService;
use App\Services\RealtimeService;
use App\Services\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * JSON API for every LMS module. The routes preserve the specified contract:
 *   GET   /api/lms/{module}
 *   POST  /api/lms/{module}
 *   PATCH /api/lms/{module}/{id}
 */
final class ApiController
{
    private const MODULES = [
        'account_access',
        'course_discovery',
        'enrollment',
        'course_materials',
        'announcements',
        'discussion_board',
        'assignment_submission',
        'quiz_lifecycle',
        'grades',
        'grade_export',
        'bulk_course_report',
        'administrative_api',
        'frontend_api_integration',
        'error_responses',
    ];

    public function __construct(
        private Config $config,
        private Database $db,
        private AuthService $auth,
        private CapabilityService $cap,
        private Validator $validator,
        private UserRepository $users,
        private CourseRepository $courses,
        private AssignmentRepository $assignments,
        private QuizRepository $quizzes,
        private GradeRepository $grades,
        private SystemRepository $system,
        private FileService $files,
        private ExportService $exports,
        private RealtimeService $realtime,
    ) {
    }

    public function me(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        if ($user === null) {
            return Http::unauthorized($response);
        }
        return Http::ok($response, [
            'user' => [
                'id' => (int) $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'display_name' => $user['display_name'],
                'role' => $user['role_name'],
            ],
            'settings' => $this->system->allSettings(),
            'modules' => self::MODULES,
        ]);
    }

    public function list(Request $request, Response $response, array $args): Response
    {
        $module = (string) $args['module'];
        if (!in_array($module, self::MODULES, true)) {
            return Http::notFound($response, 'Unknown API module: ' . $module);
        }
        $user = $request->getAttribute('user');
        if ($user === null) {
            return Http::unauthorized($response);
        }
        return match ($module) {
            'account_access' => $this->listAccountAccess($request, $response, $user),
            'course_discovery' => $this->listCourseDiscovery($request, $response, $user),
            'enrollment' => $this->listEnrollment($request, $response, $user),
            'course_materials' => $this->listCourseMaterials($request, $response, $user),
            'announcements' => $this->listAnnouncements($request, $response, $user),
            'discussion_board' => $this->listDiscussionBoard($request, $response, $user),
            'assignment_submission' => $this->listAssignments($request, $response, $user),
            'quiz_lifecycle' => $this->listQuizzes($request, $response, $user),
            'grades' => $this->listGrades($request, $response, $user),
            'grade_export' => $this->listExports($request, $response, $user),
            'bulk_course_report' => $this->listReports($request, $response, $user),
            'administrative_api' => $this->listAdmin($request, $response, $user),
            'frontend_api_integration' => $this->listFrontendIntegration($request, $response, $user),
            'error_responses' => $this->listErrorResponses($request, $response, $user),
        };
    }

    public function create(Request $request, Response $response, array $args): Response
    {
        $module = (string) $args['module'];
        if (!in_array($module, self::MODULES, true)) {
            return Http::notFound($response, 'Unknown API module: ' . $module);
        }
        $user = $request->getAttribute('user');
        // Account access supports unauthenticated flows (register, login,
        // forgot, reset); every other module requires a session.
        if ($module === 'account_access') {
            return $this->createAccountAccess($request, $response, $user ?? []);
        }
        if ($user === null) {
            return Http::unauthorized($response);
        }
        return match ($module) {
            'course_discovery' => $this->createCourse($request, $response, $user),
            'enrollment' => $this->createEnrollment($request, $response, $user),
            'course_materials' => $this->uploadMaterial($request, $response, $user),
            'announcements' => $this->createAnnouncement($request, $response, $user),
            'discussion_board' => $this->createDiscussion($request, $response, $user),
            'assignment_submission' => $this->createAssignmentOrSubmission($request, $response, $user),
            'quiz_lifecycle' => $this->createQuizOrAttempt($request, $response, $user),
            'grades' => $this->createGradeItemOrGrade($request, $response, $user),
            'grade_export' => $this->createExport($request, $response, $user),
            'bulk_course_report' => $this->createReport($request, $response, $user),
            'administrative_api' => $this->createAdminAction($request, $response, $user),
            'frontend_api_integration' => $this->createFrontendIntegration($request, $response, $user),
            'error_responses' => $this->createErrorResponses($request, $response, $user),
        };
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $module = (string) $args['module'];
        if (!in_array($module, self::MODULES, true)) {
            return Http::notFound($response, 'Unknown API module: ' . $module);
        }
        $user = $request->getAttribute('user');
        if ($user === null) {
            return Http::unauthorized($response);
        }
        return match ($module) {
            'account_access' => $this->updateAccount($request, $response, $user, (int) $args['id']),
            'course_discovery' => $this->updateCourse($request, $response, $user, (int) $args['id']),
            'enrollment' => $this->updateEnrollment($request, $response, $user, (int) $args['id']),
            'course_materials' => $this->updateMaterial($request, $response, $user, (int) $args['id']),
            'announcements' => $this->updateAnnouncement($request, $response, $user, (int) $args['id']),
            'discussion_board' => $this->updateDiscussion($request, $response, $user, (int) $args['id']),
            'assignment_submission' => $this->updateSubmission($request, $response, $user, (int) $args['id']),
            'quiz_lifecycle' => $this->updateQuiz($request, $response, $user, (int) $args['id']),
            'grades' => $this->updateGrade($request, $response, $user, (int) $args['id']),
            'grade_export' => $this->regenerateExport($request, $response, $user, (int) $args['id']),
            'bulk_course_report' => $this->regenerateReport($request, $response, $user, (int) $args['id']),
            'administrative_api' => $this->updateAdminAction($request, $response, $user, (int) $args['id']),
            'frontend_api_integration' => $this->updateFrontendIntegration($request, $response, $user, (int) $args['id']),
            'error_responses' => $this->updateErrorResponses($request, $response, $user, (int) $args['id']),
        };
    }

    // ---- LMS-01 Account access ----

    private function listAccountAccess(Request $request, Response $response, array $user): Response
    {
        $userId = (int) $user['id'];
        $isAdmin = $this->cap->isAdmin($user);
        return Http::ok($response, [
            'account' => [
                'id' => $userId,
                'username' => $user['username'],
                'email' => $user['email'],
                'display_name' => $user['display_name'],
                'role' => $user['role_name'],
            ],
            'access_log' => $this->users->accessLog($userId, $isAdmin && ($request->getQueryParams()['scope'] ?? '') === 'all'),
        ]);
    }

    private function createAccountAccess(Request $request, Response $response, array $user): Response
    {
        $data = Http::body($request);
        $action = (string) ($data['action'] ?? '');
        $ip = (string) ($request->getServerParams()['REMOTE_ADDR'] ?? '127.0.0.1');

        return match ($action) {
            'register' => $this->apiRegister($request, $response, $data, $ip),
            'login' => $this->apiLogin($response, $data, $ip),
            'logout' => $this->apiLogout($request, $response, $user),
            'forgot' => $this->apiForgot($response, $data),
            'reset' => $this->apiReset($response, $data),
            default => Http::validation($response, ['action' => 'action must be register, login, logout, forgot or reset']),
        };
    }

    private function apiRegister(Request $request, Response $response, array $data, string $ip): Response
    {
        $errors = $this->validator->validate($data, [
            'username' => ['required', 'min:3'],
            'email' => ['required', 'email'],
            'password' => ['required', 'min:8'],
            'display_name' => ['required', 'min:2'],
        ]);
        if ($errors !== []) {
            return Http::validation($response, $errors);
        }
        $result = $this->auth->register((string) $data['username'], (string) $data['email'], (string) $data['password'], (string) $data['display_name'], $ip);
        if (isset($result['error'])) {
            return Http::validation($response, ['identifier' => $result['error']]);
        }
        return Http::created($response, ['user' => $this->publicUser($result['user']), 'redirect' => '/dashboard'])
            ->withAddedHeader('Set-Cookie', AuthService::cookieHeader($result['token'], $this->config->sessionLifetimeMinutes()));
    }

    private function apiLogin(Response $response, array $data, string $ip): Response
    {
        $errors = $this->validator->required($data, ['identifier', 'password']);
        if ($errors !== []) {
            return Http::validation($response, $errors);
        }
        $result = $this->auth->attemptLogin((string) $data['identifier'], (string) $data['password'], $ip);
        if (isset($result['error'])) {
            return Http::validation($response, ['identifier' => $result['error']]);
        }
        return Http::ok($response, ['user' => $this->publicUser($result['user']), 'redirect' => '/dashboard'])
            ->withAddedHeader('Set-Cookie', AuthService::cookieHeader($result['token'], $this->config->sessionLifetimeMinutes()));
    }

    private function apiLogout(Request $request, Response $response, array $user): Response
    {
        if (!isset($user['id'])) {
            return Http::unauthorized($response);
        }
        $token = AuthService::tokenFromRequest($request);
        if ($token !== null) {
            $this->auth->destroyToken($token);
        }
        $this->auth->logAccess((int) $user['id'], 'logout');
        return Http::ok($response, ['logged_out' => true, 'redirect' => '/login']);
    }

    private function apiForgot(Response $response, array $data): Response
    {
        $identifier = trim((string) ($data['identifier'] ?? ''));
        if ($identifier === '') {
            return Http::validation($response, ['identifier' => 'The identifier field is required']);
        }
        $result = $this->auth->createPasswordReset($identifier);
        if (isset($result['error'])) {
            return Http::validation($response, ['identifier' => $result['error']]);
        }
        return Http::ok($response, ['sent' => true, 'token' => $result['token']]);
    }

    private function apiReset(Response $response, array $data): Response
    {
        $errors = $this->validator->validate($data, ['token' => ['required'], 'password' => ['required', 'min:8']]);
        if ($errors !== []) {
            return Http::validation($response, $errors);
        }
        $result = $this->auth->resetPassword((string) $data['token'], (string) $data['password']);
        if (isset($result['error'])) {
            return Http::validation($response, ['token' => $result['error']]);
        }
        return Http::ok($response, ['reset' => true, 'redirect' => '/login']);
    }

    private function updateAccount(Request $request, Response $response, array $user, int $id): Response
    {
        if ($id !== (int) $user['id'] && !$this->cap->isAdmin($user)) {
            return Http::deny($response, 'You can only update your own account');
        }
        $target = $this->users->find($id);
        if ($target === null) {
            return Http::notFound($response);
        }
        $data = Http::body($request);
        $update = [];
        if (isset($data['display_name'])) {
            $update['display_name'] = trim((string) $data['display_name']);
        }
        if (isset($data['email'])) {
            $update['email'] = trim((string) $data['email']);
        }
        if (isset($data['password']) && $data['password'] !== '') {
            if (strlen((string) $data['password']) < 8) {
                return Http::validation($response, ['password' => 'Password must be at least 8 characters']);
            }
            $update['password_hash'] = password_hash((string) $data['password'], PASSWORD_DEFAULT);
        }
        if ($update === []) {
            return Http::validation($response, ['update' => 'No fields provided to update']);
        }
        $this->users->update($id, $update);
        $this->system->audit(['user_id' => $user['id'], 'action' => 'account.update', 'entity_type' => 'users', 'entity_id' => $id, 'detail_json' => json_encode(array_keys($update))]);
        return Http::ok($response, ['updated' => true, 'account' => ['id' => $id] + $update]);
    }

    // ---- LMS-02 Course discovery ----

    private function listCourseDiscovery(Request $request, Response $response, array $user): Response
    {
        $q = $request->getQueryParams();
        $includePrivate = $this->cap->isAdmin($user) || $this->cap->isInstructor($user);
        $courses = $this->courses->discover($q, $includePrivate);
        return Http::ok($response, [
            'filters' => ['categories' => $this->courses->categories(), 'semesters' => $this->courses->semesters(), 'instructors' => $this->courses->instructors()],
            'courses' => array_map(fn ($c) => $this->publicCourse($c, (int) $user['id']), $courses),
        ]);
    }

    private function createCourse(Request $request, Response $response, array $user): Response
    {
        if (!$this->cap->has($user, 'course.create')) {
            return Http::deny($response);
        }
        $data = Http::body($request);
        $errors = $this->validator->required($data, ['title']);
        if ($errors !== []) {
            return Http::validation($response, $errors);
        }
        $course = [
            'title' => trim((string) $data['title']),
            'category' => trim((string) ($data['category'] ?? 'General')),
            'semester' => trim((string) ($data['semester'] ?? 'Fall 2026')),
            'description' => (string) ($data['description'] ?? ''),
            'visibility' => (string) ($data['visibility'] ?? 'public'),
            'status' => (string) ($data['status'] ?? 'open'),
            'instructor_id' => $this->cap->isAdmin($user) && isset($data['instructor_id']) ? (int) $data['instructor_id'] : (int) $user['id'],
        ];
        $id = $this->courses->create($course);
        $this->system->audit(['user_id' => $user['id'], 'action' => 'course.create', 'entity_type' => 'courses', 'entity_id' => $id, 'detail_json' => json_encode(['title' => $course['title']])]);
        return Http::created($response, ['course' => $this->publicCourse($this->courses->find($id), (int) $user['id'])]);
    }

    private function updateCourse(Request $request, Response $response, array $user, int $id): Response
    {
        $course = $this->courses->find($id);
        if ($course === null) {
            return Http::notFound($response);
        }
        if (!$this->cap->has($user, 'course.edit.any') && !($this->cap->has($user, 'course.edit.own') && $this->cap->owns($user, $course))) {
            return Http::deny($response);
        }
        $data = Http::body($request);
        $update = $this->validator->take($data, ['title', 'category', 'semester', 'description', 'visibility', 'status']);
        if ($update === []) {
            return Http::validation($response, ['update' => 'No fields provided to update']);
        }
        if ($this->cap->isAdmin($user) && isset($data['instructor_id'])) {
            $update['instructor_id'] = (int) $data['instructor_id'];
        }
        $this->courses->update($id, $update);
        $this->system->audit(['user_id' => $user['id'], 'action' => 'course.update', 'entity_type' => 'courses', 'entity_id' => $id, 'detail_json' => json_encode(array_keys($update))]);
        return Http::ok($response, ['course' => $this->publicCourse($this->courses->find($id), (int) $user['id'])]);
    }

    // ---- LMS-03 Enrollment ----

    private function listEnrollment(Request $request, Response $response, array $user): Response
    {
        $q = $request->getQueryParams();
        if (isset($q['course_id'])) {
            $course = $this->courses->find((int) $q['course_id']);
            if ($course === null) {
                return Http::notFound($response);
            }
            if (!$this->cap->has($user, 'enrollment.roster') && !$this->cap->has($user, 'enrollment.manage')) {
                return Http::deny($response);
            }
            if (!$this->cap->isAdmin($user) && (int) $course['instructor_id'] !== (int) $user['id']) {
                return Http::deny($response);
            }
            return Http::ok($response, ['course' => ['id' => (int) $course['id'], 'title' => $course['title']], 'roster' => $this->courses->roster((int) $course['id'])]);
        }
        if ($this->cap->has($user, 'enrollment.manage')) {
            return Http::ok($response, ['enrollments' => $this->courses->allEnrollments((string) ($q['status'] ?? ''))]);
        }
        return Http::ok($response, ['enrollments' => $this->courses->enrollmentsForUser((int) $user['id'])]);
    }

    private function createEnrollment(Request $request, Response $response, array $user): Response
    {
        $data = Http::body($request);
        $courseId = (int) ($data['course_id'] ?? 0);
        $course = $this->courses->find($courseId);
        if ($course === null) {
            return Http::notFound($response);
        }
        $isManager = $this->cap->has($user, 'enrollment.manage') || ($this->cap->has($user, 'enrollment.roster') && $this->cap->owns($user, $course));
        if ($this->cap->has($user, 'enrollment.self')) {
            if ($course['status'] === 'closed') {
                return Http::error($response, 409, 'COURSE_CLOSED', 'This course is closed and no longer accepts enrollments');
            }
            if (!$isManager) {
                $this->courses->enroll($courseId, (int) $user['id']);
                $this->system->audit(['user_id' => $user['id'], 'action' => 'enrollment.create', 'entity_type' => 'enrollments', 'entity_id' => 0, 'detail_json' => json_encode(['course_id' => $courseId])]);
                return Http::created($response, ['enrolled' => true, 'course' => $course['title']]);
            }
        }
        if ($isManager) {
            $targetUserId = (int) ($data['user_id'] ?? $user['id']);
            $status = (string) ($data['status'] ?? 'enrolled');
            $this->courses->enroll($courseId, $targetUserId, $status);
            $this->system->audit(['user_id' => $user['id'], 'action' => 'enrollment.create', 'entity_type' => 'enrollments', 'entity_id' => 0, 'detail_json' => json_encode(['course_id' => $courseId, 'user_id' => $targetUserId])]);
            return Http::created($response, ['enrolled' => true, 'course' => $course['title'], 'user_id' => $targetUserId]);
        }
        return Http::deny($response);
    }

    private function updateEnrollment(Request $request, Response $response, array $user, int $id): Response
    {
        $row = $this->db->select('SELECT e.*, c.instructor_id AS course_instructor FROM enrollments e JOIN courses c ON c.id = e.course_id WHERE e.id = ?', [$id]);
        if ($row === []) {
            return Http::notFound($response);
        }
        $row = $row[0];
        if (!$this->cap->has($user, 'enrollment.manage') && !($this->cap->has($user, 'enrollment.roster') && (int) $row['course_instructor'] === (int) $user['id'])) {
            return Http::deny($response);
        }
        $data = Http::body($request);
        $status = (string) ($data['status'] ?? '');
        if (!in_array($status, ['enrolled', 'dropped', 'pending'], true)) {
            return Http::validation($response, ['status' => 'status must be enrolled, dropped or pending']);
        }
        $this->courses->setEnrollmentStatus($id, $status);
        $this->system->audit(['user_id' => $user['id'], 'action' => 'enrollment.update', 'entity_type' => 'enrollments', 'entity_id' => $id, 'detail_json' => json_encode(['status' => $status])]);
        return Http::ok($response, ['updated' => true, 'enrollment_id' => $id, 'status' => $status]);
    }

    // ---- LMS-04 Course materials ----

    private function listCourseMaterials(Request $request, Response $response, array $user): Response
    {
        $courseId = (int) ($request->getQueryParams()['course_id'] ?? 0);
        if ($courseId === 0) {
            return Http::validation($response, ['course_id' => 'course_id is required']);
        }
        $course = $this->courses->find($courseId);
        if ($course === null) {
            return Http::notFound($response);
        }
        if (!$this->memberOf($user, $course)) {
            return Http::deny($response, 'You are not a member of this course');
        }
        return Http::ok($response, ['materials' => $this->courses->materialsForCourse($courseId)]);
    }

    private function uploadMaterial(Request $request, Response $response, array $user): Response
    {
        $data = Http::body($request);
        $parsed = is_array($request->getParsedBody()) ? $request->getParsedBody() : [];
        $data = array_merge($parsed, $data);
        $courseId = (int) ($data['course_id'] ?? 0);
        $course = $this->courses->find($courseId);
        if ($course === null) {
            return Http::notFound($response);
        }
        if (!$this->cap->has($user, 'material.upload.any') && !($this->cap->has($user, 'material.upload.own') && $this->cap->owns($user, $course))) {
            return Http::deny($response);
        }
        $files = $request->getUploadedFiles();
        $file = $files['file'] ?? null;
        if (!$file instanceof \Psr\Http\Message\UploadedFileInterface) {
            return Http::validation($response, ['file' => 'A file upload is required']);
        }
        $title = trim((string) ($data['title'] ?? $file->getClientFilename() ?? 'Untitled material'));
        $stored = $this->files->storePsr7($file, 'material');
        if (isset($stored['error'])) {
            return Http::validation($response, ['file' => $stored['error']]);
        }
        $id = $this->courses->addMaterial([
            'course_id' => $courseId,
            'uploader_id' => (int) $user['id'],
            'title' => $title,
            'description' => (string) ($data['description'] ?? ''),
            'filename' => $stored['filename'],
            'stored_path' => $stored['stored_path'],
            'mime_type' => $stored['mime_type'],
            'size_bytes' => $stored['size_bytes'],
        ]);
        $this->system->audit(['user_id' => $user['id'], 'action' => 'material.create', 'entity_type' => 'materials', 'entity_id' => $id, 'detail_json' => json_encode(['course_id' => $courseId])]);
        return Http::created($response, ['material' => $this->courses->material($id)]);
    }

    private function updateMaterial(Request $request, Response $response, array $user, int $id): Response
    {
        $material = $this->courses->material($id);
        if ($material === null) {
            return Http::notFound($response);
        }
        $course = $this->courses->find((int) $material['course_id']);
        if (!$this->cap->has($user, 'material.upload.any') && !($this->cap->has($user, 'material.upload.own') && $this->cap->owns($user, $course))) {
            return Http::deny($response);
        }
        $update = $this->validator->take(Http::body($request), ['title', 'description']);
        if ($update === []) {
            return Http::validation($response, ['update' => 'No fields provided to update']);
        }
        $this->courses->updateMaterial($id, $update);
        return Http::ok($response, ['material' => $this->courses->material($id)]);
    }

    // ---- LMS-05 Announcements ----

    private function listAnnouncements(Request $request, Response $response, array $user): Response
    {
        $q = $request->getQueryParams();
        if (isset($q['course_id']) && $q['course_id'] !== '') {
            $course = $this->courses->find((int) $q['course_id']);
            if ($course === null) {
                return Http::notFound($response);
            }
            if (!$this->memberOf($user, $course)) {
                return Http::deny($response, 'You are not a member of this course');
            }
            return Http::ok($response, ['announcements' => $this->courses->announcementsForCourse((int) $course['id'])]);
        }
        if ($this->cap->isInstructor($user) || $this->cap->isAdmin($user)) {
            $courses = $this->courses->discover(['status' => 'all', 'visibility' => 'all'], true);
            $out = [];
            foreach ($courses as $course) {
                if ($this->cap->isAdmin($user) || $this->cap->owns($user, $course)) {
                    $out[] = $this->courses->announcementsForCourse((int) $course['id']);
                }
            }
            return Http::ok($response, ['announcements' => array_merge(...array_filter($out))]);
        }
        return Http::ok($response, ['announcements' => $this->courses->announcementsForUser((int) $user['id'])]);
    }

    private function createAnnouncement(Request $request, Response $response, array $user): Response
    {
        $data = Http::body($request);
        $errors = $this->validator->required($data, ['course_id', 'title']);
        if ($errors !== []) {
            return Http::validation($response, $errors);
        }
        $course = $this->courses->find((int) $data['course_id']);
        if ($course === null) {
            return Http::notFound($response);
        }
        if (!$this->cap->has($user, 'announcement.publish.any') && !($this->cap->has($user, 'announcement.publish.own') && $this->cap->owns($user, $course))) {
            return Http::deny($response);
        }
        $id = $this->courses->addAnnouncement([
            'course_id' => (int) $course['id'],
            'instructor_id' => (int) $user['id'],
            'title' => trim((string) $data['title']),
            'body' => (string) ($data['body'] ?? ''),
        ]);
        $this->realtime->publish($this->realtime->channelForCourse((int) $course['id']), ['type' => 'announcement', 'announcement' => $this->courses->announcement($id)]);
        $this->system->audit(['user_id' => $user['id'], 'action' => 'announcement.create', 'entity_type' => 'announcements', 'entity_id' => $id, 'detail_json' => json_encode(['course_id' => (int) $course['id']])]);
        return Http::created($response, ['announcement' => $this->courses->announcement($id)]);
    }

    private function updateAnnouncement(Request $request, Response $response, array $user, int $id): Response
    {
        $announcement = $this->courses->announcement($id);
        if ($announcement === null) {
            return Http::notFound($response);
        }
        $course = $this->courses->find((int) $announcement['course_id']);
        if (!$this->cap->has($user, 'announcement.publish.any') && !($this->cap->has($user, 'announcement.publish.own') && $this->cap->owns($user, $course))) {
            return Http::deny($response);
        }
        $update = $this->validator->take(Http::body($request), ['title', 'body']);
        if ($update === []) {
            return Http::validation($response, ['update' => 'No fields provided to update']);
        }
        $this->courses->updateAnnouncement($id, $update);
        return Http::ok($response, ['announcement' => $this->courses->announcement($id)]);
    }

    // ---- LMS-06 Discussion board ----

    private function listDiscussionBoard(Request $request, Response $response, array $user): Response
    {
        $q = $request->getQueryParams();
        $courseId = (int) ($q['course_id'] ?? 0);
        $threadId = (int) ($q['thread_id'] ?? 0);
        if ($threadId > 0) {
            $thread = $this->courses->discussion($threadId);
            if ($thread === null) {
                return Http::notFound($response);
            }
            $course = $this->courses->find((int) $thread['course_id']);
            if (!$this->memberOf($user, $course)) {
                return Http::deny($response, 'You are not a member of this course');
            }
            return Http::ok($response, ['thread' => $thread, 'replies' => $this->courses->repliesForThread($threadId)]);
        }
        if ($courseId === 0) {
            return Http::validation($response, ['course_id' => 'course_id is required']);
        }
        $course = $this->courses->find($courseId);
        if ($course === null) {
            return Http::notFound($response);
        }
        if (!$this->memberOf($user, $course)) {
            return Http::deny($response, 'You are not a member of this course');
        }
        return Http::ok($response, ['threads' => $this->courses->threadsForCourse($courseId, (string) ($q['q'] ?? ''))]);
    }

    private function createDiscussion(Request $request, Response $response, array $user): Response
    {
        $data = Http::body($request);
        $errors = $this->validator->required($data, ['course_id', 'subject']);
        if ($errors !== []) {
            return Http::validation($response, $errors);
        }
        $course = $this->courses->find((int) $data['course_id']);
        if ($course === null) {
            return Http::notFound($response);
        }
        if (!$this->memberOf($user, $course)) {
            return Http::deny($response, 'You are not a member of this course');
        }
        $parentId = (int) ($data['parent_id'] ?? 0);
        if ($parentId > 0) {
            $parent = $this->courses->discussion($parentId);
            if ($parent === null || (int) $parent['course_id'] !== (int) $course['id']) {
                return Http::notFound($response, 'Parent thread not found in this course');
            }
        }
        $id = $this->courses->addDiscussion([
            'course_id' => (int) $course['id'],
            'author_id' => (int) $user['id'],
            'parent_id' => $parentId > 0 ? $parentId : null,
            'subject' => trim((string) $data['subject']),
            'body' => (string) ($data['body'] ?? ''),
        ]);
        $this->realtime->publish($this->realtime->channelForCourse((int) $course['id']), ['type' => 'discussion', 'discussion' => $this->courses->discussion($id)]);
        return Http::created($response, ['discussion' => $this->courses->discussion($id)]);
    }

    private function updateDiscussion(Request $request, Response $response, array $user, int $id): Response
    {
        $post = $this->courses->discussion($id);
        if ($post === null) {
            return Http::notFound($response);
        }
        $canEditOwn = $this->cap->has($user, 'discussion.edit.own') && (int) $post['author_id'] === (int) $user['id'];
        $canEditAny = $this->cap->has($user, 'discussion.edit.any');
        if (!$canEditOwn && !$canEditAny) {
            return Http::deny($response);
        }
        $data = Http::body($request);
        if (isset($data['subject']) && $data['subject'] !== '') {
            $update['subject'] = trim((string) $data['subject']);
        }
        if (isset($data['body'])) {
            $update['body'] = (string) $data['body'];
        }
        if (empty($update)) {
            return Http::validation($response, ['update' => 'No fields provided to update']);
        }
        $this->courses->updateDiscussion($id, $update);
        return Http::ok($response, ['discussion' => $this->courses->discussion($id)]);
    }

    // ---- LMS-07 Assignment submission ----

    private function listAssignments(Request $request, Response $response, array $user): Response
    {
        $q = $request->getQueryParams();
        if (isset($q['submission_id'])) {
            $submission = $this->assignments->submission((int) $q['submission_id']);
            if ($submission === null) {
                return Http::notFound($response);
            }
            $course = $this->courses->find((int) $submission['course_id']);
            if (!$this->cap->has($user, 'assignment.grade.any') && !($this->cap->has($user, 'assignment.grade.own') && $this->cap->owns($user, $course)) && (int) $submission['id'] !== (int) $user['id']) {
                return Http::deny($response);
            }
            return Http::ok($response, ['submission' => $submission]);
        }
        if (isset($q['assignment_id'])) {
            $assignment = $this->assignments->assignment((int) $q['assignment_id']);
            if ($assignment === null) {
                return Http::notFound($response);
            }
            $course = $this->courses->find((int) $assignment['course_id']);
            if (!$this->memberOf($user, $course)) {
                return Http::deny($response, 'You are not a member of this course');
            }
            if ($this->cap->has($user, 'assignment.grade.any') || ($this->cap->has($user, 'assignment.grade.own') && $this->cap->owns($user, $course))) {
                return Http::ok($response, ['assignment' => $assignment, 'submissions' => $this->assignments->submissionsForAssignment((int) $assignment['id'])]);
            }
            return Http::ok($response, ['assignment' => $assignment, 'my_submission' => $this->assignments->submissionForStudent((int) $assignment['id'], (int) $user['id'])]);
        }
        $courseId = (int) ($q['course_id'] ?? 0);
        if ($courseId === 0) {
            return Http::validation($response, ['course_id' => 'course_id is required']);
        }
        $course = $this->courses->find($courseId);
        if ($course === null) {
            return Http::notFound($response);
        }
        if (!$this->memberOf($user, $course)) {
            return Http::deny($response, 'You are not a member of this course');
        }
        return Http::ok($response, ['assignments' => $this->assignments->assignmentsForCourse($courseId)]);
    }

    private function createAssignmentOrSubmission(Request $request, Response $response, array $user): Response
    {
        $data = Http::body($request);
        $parsed = is_array($request->getParsedBody()) ? $request->getParsedBody() : [];
        $data = array_merge($parsed, $data);
        $action = (string) ($data['action'] ?? 'submit');
        if ($action === 'create') {
            return $this->createAssignment($request, $response, $user, $data);
        }
        if ($action !== 'submit') {
            return Http::validation($response, ['action' => 'action must be create or submit']);
        }
        $assignmentId = (int) ($data['assignment_id'] ?? 0);
        $assignment = $this->assignments->assignment($assignmentId);
        if ($assignment === null) {
            return Http::notFound($response);
        }
        $course = $this->courses->find((int) $assignment['course_id']);
        if (!$this->cap->has($user, 'assignment.submit')) {
            return Http::deny($response);
        }
        if (!$this->courses->isEnrolled((int) $course['id'], (int) $user['id'])) {
            return Http::deny($response, 'You must be enrolled in this course to submit');
        }
        $files = $request->getUploadedFiles();
        $file = $files['file'] ?? null;
        if (!$file instanceof \Psr\Http\Message\UploadedFileInterface) {
            return Http::validation($response, ['file' => 'A file upload is required']);
        }
        $stored = $this->files->storePsr7($file, 'submission');
        if (isset($stored['error'])) {
            return Http::validation($response, ['file' => $stored['error']]);
        }
        $id = $this->assignments->submit([
            'assignment_id' => $assignmentId,
            'student_id' => (int) $user['id'],
            'filename' => $stored['filename'],
            'stored_path' => $stored['stored_path'],
            'mime_type' => $stored['mime_type'],
            'size_bytes' => $stored['size_bytes'],
        ]);
        return Http::created($response, ['submission' => $this->assignments->submission($id)]);
    }

    private function createAssignment(Request $request, Response $response, array $user, array $data): Response
    {
        $errors = $this->validator->required($data, ['course_id', 'title']);
        if ($errors !== []) {
            return Http::validation($response, $errors);
        }
        $course = $this->courses->find((int) $data['course_id']);
        if ($course === null) {
            return Http::notFound($response);
        }
        if (!$this->cap->has($user, 'assignment.manage.any') && !($this->cap->has($user, 'assignment.manage.own') && $this->cap->owns($user, $course))) {
            return Http::deny($response);
        }
        $id = $this->assignments->create([
            'course_id' => (int) $course['id'],
            'instructor_id' => (int) $user['id'],
            'title' => trim((string) $data['title']),
            'description' => (string) ($data['description'] ?? ''),
            'due_at' => (string) ($data['due_at'] ?? ''),
            'max_points' => (int) ($data['max_points'] ?? 100),
        ]);
        $this->system->audit(['user_id' => $user['id'], 'action' => 'assignment.create', 'entity_type' => 'assignments', 'entity_id' => $id, 'detail_json' => json_encode(['course_id' => (int) $course['id']])]);
        return Http::created($response, ['assignment' => $this->assignments->assignment($id)]);
    }

    private function updateSubmission(Request $request, Response $response, array $user, int $id): Response
    {
        $submission = $this->assignments->submission($id);
        if ($submission === null) {
            return Http::notFound($response);
        }
        $course = $this->courses->find((int) $submission['course_id']);
        if (!$this->cap->has($user, 'assignment.grade.any') && !($this->cap->has($user, 'assignment.grade.own') && $this->cap->owns($user, $course))) {
            return Http::deny($response);
        }
        $data = Http::body($request);
        $this->assignments->grade($id, (string) ($data['status'] ?? 'graded'));
        if (isset($data['score'])) {
            $assignment = $this->assignments->assignment((int) $submission['assignment_id']);
            $item = $this->db->first('SELECT * FROM grade_items WHERE course_id = ? AND name = ?', [(int) $assignment['course_id'], $assignment['title'] . ' (submission)']);
            if ($item === null) {
                $itemId = $this->grades->addItem([
                    'course_id' => (int) $assignment['course_id'],
                    'instructor_id' => (int) $user['id'],
                    'name' => $assignment['title'] . ' (submission)',
                    'max_points' => (int) $assignment['max_points'],
                    'weight' => 1,
                ]);
            } else {
                $itemId = (int) $item['id'];
            }
            $this->grades->saveGrade([
                'grade_item_id' => $itemId,
                'student_id' => (int) $submission['student_id'],
                'score' => (float) $data['score'],
                'feedback' => (string) ($data['feedback'] ?? ''),
                'graded_by' => (int) $user['id'],
            ]);
        }
        $this->system->audit(['user_id' => $user['id'], 'action' => 'submission.grade', 'entity_type' => 'submissions', 'entity_id' => $id, 'detail_json' => json_encode(['status' => $data['status'] ?? 'graded'])]);
        return Http::ok($response, ['submission' => $this->assignments->submission($id)]);
    }

    // ---- LMS-08 Quiz lifecycle ----

    private function listQuizzes(Request $request, Response $response, array $user): Response
    {
        $q = $request->getQueryParams();
        if (isset($q['quiz_id'])) {
            $quiz = $this->quizzes->quiz((int) $q['quiz_id']);
            if ($quiz === null) {
                return Http::notFound($response);
            }
            $course = $this->courses->find((int) $quiz['course_id']);
            if (!$this->memberOf($user, $course)) {
                return Http::deny($response, 'You are not a member of this course');
            }
            $isManager = $this->cap->has($user, 'quiz.manage.any') || ($this->cap->has($user, 'quiz.manage.own') && $this->cap->owns($user, $course));
            $questions = $this->quizzes->questionsForQuiz((int) $quiz['id']);
            if (!$isManager) {
                $questions = array_map(static function (array $question): array {
                    $question['correct_answer'] = '';
                    return $question;
                }, $questions);
            }
            return Http::ok($response, ['quiz' => $quiz, 'is_manager' => $isManager, 'questions' => $questions]);
        }
        if (isset($q['attempt_id'])) {
            $attempt = $this->quizzes->attempt((int) $q['attempt_id']);
            if ($attempt === null) {
                return Http::notFound($response);
            }
            $isOwner = (int) $attempt['student_id'] === (int) $user['id'];
            $course = $this->courses->find((int) $attempt['course_id']);
            $isManager = $this->cap->has($user, 'quiz.review.any') || ($this->cap->has($user, 'quiz.review.own') && $this->cap->owns($user, $course));
            if (!$isOwner && !$isManager) {
                return Http::deny($response);
            }
            $answers = $this->quizzes->answersForAttempt((int) $attempt['id']);
            return Http::ok($response, ['attempt' => $attempt, 'answers' => $answers]);
        }
        $courseId = (int) ($q['course_id'] ?? 0);
        if ($courseId === 0) {
            return Http::validation($response, ['course_id' => 'course_id is required']);
        }
        $course = $this->courses->find($courseId);
        if ($course === null) {
            return Http::notFound($response);
        }
        if (!$this->memberOf($user, $course)) {
            return Http::deny($response, 'You are not a member of this course');
        }
        $isManager = $this->cap->has($user, 'quiz.manage.any') || ($this->cap->has($user, 'quiz.manage.own') && $this->cap->owns($user, $course));
        $quizzes = $this->quizzes->quizzesForCourse($courseId, $isManager);
        $out = [];
        foreach ($quizzes as $quiz) {
            $quiz['is_manager'] = $isManager;
            if ($isManager) {
                $quiz['attempts'] = $this->quizzes->attemptsForQuiz((int) $quiz['id']);
            } else {
                $attempt = $this->quizzes->latestAttempt((int) $quiz['id'], (int) $user['id']);
                $quiz['my_attempt'] = $attempt;
            }
            $out[] = $quiz;
        }
        return Http::ok($response, ['quizzes' => $out]);
    }

    private function createQuizOrAttempt(Request $request, Response $response, array $user): Response
    {
        $data = Http::body($request);
        $action = (string) ($data['action'] ?? 'quiz');
        if ($action === 'attempt') {
            return $this->submitQuizAttempt($request, $response, $user, $data);
        }
        if ($action === 'question') {
            return $this->addQuizQuestion($response, $user, $data);
        }
        if ($action !== 'quiz') {
            return Http::validation($response, ['action' => 'action must be quiz, question or attempt']);
        }
        return $this->createQuiz($response, $user, $data);
    }

    private function createQuiz(Response $response, array $user, array $data): Response
    {
        $errors = $this->validator->required($data, ['course_id', 'title']);
        if ($errors !== []) {
            return Http::validation($response, $errors);
        }
        $course = $this->courses->find((int) $data['course_id']);
        if ($course === null) {
            return Http::notFound($response);
        }
        if (!$this->cap->has($user, 'quiz.manage.any') && !($this->cap->has($user, 'quiz.manage.own') && $this->cap->owns($user, $course))) {
            return Http::deny($response);
        }
        $id = $this->quizzes->createQuiz([
            'course_id' => (int) $course['id'],
            'instructor_id' => (int) $user['id'],
            'title' => trim((string) $data['title']),
            'description' => (string) ($data['description'] ?? ''),
            'time_limit_minutes' => (int) ($data['time_limit_minutes'] ?? 10),
            'published' => isset($data['published']) ? ((int) $data['published'] === 1 ? 1 : 0) : 1,
        ]);
        $this->system->audit(['user_id' => $user['id'], 'action' => 'quiz.create', 'entity_type' => 'quizzes', 'entity_id' => $id, 'detail_json' => json_encode(['course_id' => (int) $course['id']])]);
        return Http::created($response, ['quiz' => $this->quizzes->quiz($id)]);
    }

    private function addQuizQuestion(Response $response, array $user, array $data): Response
    {
        $errors = $this->validator->required($data, ['quiz_id', 'prompt']);
        if ($errors !== []) {
            return Http::validation($response, $errors);
        }
        $quiz = $this->quizzes->quiz((int) $data['quiz_id']);
        if ($quiz === null) {
            return Http::notFound($response);
        }
        $course = $this->courses->find((int) $quiz['course_id']);
        if (!$this->cap->has($user, 'quiz.manage.any') && !($this->cap->has($user, 'quiz.manage.own') && $this->cap->owns($user, $course))) {
            return Http::deny($response);
        }
        $options = is_array($data['options'] ?? null) ? $data['options'] : [];
        $id = $this->quizzes->addQuestion([
            'quiz_id' => (int) $quiz['id'],
            'prompt' => (string) $data['prompt'],
            'question_type' => (string) ($data['question_type'] ?? 'multiple_choice'),
            'options_json' => (string) json_encode($options),
            'correct_answer' => (string) ($data['correct_answer'] ?? ''),
            'points' => (int) ($data['points'] ?? 1),
        ]);
        return Http::created($response, ['question' => $this->quizzes->question($id)]);
    }

    private function submitQuizAttempt(Request $request, Response $response, array $user, array $data): Response
    {
        $quizId = (int) ($data['quiz_id'] ?? 0);
        $quiz = $this->quizzes->quiz($quizId);
        if ($quiz === null) {
            return Http::notFound($response);
        }
        if (!$this->cap->has($user, 'quiz.take')) {
            return Http::deny($response);
        }
        $course = $this->courses->find((int) $quiz['course_id']);
        if (!$this->courses->isEnrolled((int) $course['id'], (int) $user['id'])) {
            return Http::deny($response, 'You must be enrolled in this course to take the quiz');
        }
        $questions = $this->quizzes->questionsForQuiz($quizId);
        $answers = is_array($data['answers'] ?? null) ? $data['answers'] : [];
        $attemptId = (int) ($data['attempt_id'] ?? 0);
        if ($attemptId === 0) {
            $attempt = $this->quizzes->activeAttempt($quizId, (int) $user['id']);
            if ($attempt === null) {
                $attemptId = $this->quizzes->startAttempt($quizId, (int) $user['id']);
            } else {
                $attemptId = (int) $attempt['id'];
            }
        } else {
            $existing = $this->quizzes->attempt($attemptId);
            if ($existing === null || (int) $existing['student_id'] !== (int) $user['id']) {
                return Http::deny($response);
            }
            if ($existing['status'] === 'completed') {
                return Http::error($response, 409, 'ATTEMPT_COMPLETED', 'This attempt has already been completed');
            }
        }
        $score = 0.0;
        foreach ($questions as $question) {
            $answer = (string) ($answers[$question['id']] ?? '');
            $isCorrect = $answer !== '' && strtolower($answer) === strtolower((string) $question['correct_answer']);
            $points = $isCorrect ? (float) $question['points'] : 0.0;
            $score += $points;
            $this->quizzes->saveAnswer($attemptId, (int) $question['id'], $answer, $isCorrect, $points);
        }
        $this->quizzes->completeAttempt($attemptId, $score);
        $max = $this->quizzes->maxPossibleScore($quizId);
        $this->system->audit(['user_id' => $user['id'], 'action' => 'quiz.attempt', 'entity_type' => 'quiz_attempts', 'entity_id' => $attemptId, 'detail_json' => json_encode(['quiz_id' => $quizId, 'score' => $score])]);
        return Http::created($response, ['attempt' => $this->quizzes->attempt($attemptId), 'score' => $score, 'max_score' => $max]);
    }

    private function updateQuiz(Request $request, Response $response, array $user, int $id): Response
    {
        $quiz = $this->quizzes->quiz($id);
        if ($quiz === null) {
            return Http::notFound($response);
        }
        $course = $this->courses->find((int) $quiz['course_id']);
        if (!$this->cap->has($user, 'quiz.manage.any') && !($this->cap->has($user, 'quiz.manage.own') && $this->cap->owns($user, $course))) {
            return Http::deny($response);
        }
        $update = $this->validator->take(Http::body($request), ['title', 'description', 'time_limit_minutes']);
        if (isset(Http::body($request)['published'])) {
            $update['published'] = (int) Http::body($request)['published'] === 1 ? 1 : 0;
        }
        if ($update === []) {
            return Http::validation($response, ['update' => 'No fields provided to update']);
        }
        $this->quizzes->updateQuiz($id, $update);
        return Http::ok($response, ['quiz' => $this->quizzes->quiz($id)]);
    }

    // ---- LMS-09 Grades ----

    private function listGrades(Request $request, Response $response, array $user): Response
    {
        $q = $request->getQueryParams();
        if (isset($q['course_id'])) {
            $course = $this->courses->find((int) $q['course_id']);
            if ($course === null) {
                return Http::notFound($response);
            }
            $isManager = $this->cap->has($user, 'grade.manage.any') || ($this->cap->has($user, 'grade.manage.own') && $this->cap->owns($user, $course));
            if (!$isManager) {
                return Http::deny($response);
            }
            return Http::ok($response, [
                'course' => ['id' => (int) $course['id'], 'title' => $course['title']],
                'gradebook' => $this->grades->gradebookForCourse((int) $course['id']),
                'averages' => $this->grades->averagesForCourse((int) $course['id']),
            ]);
        }
        if (!$this->cap->has($user, 'grade.view.own')) {
            return Http::deny($response);
        }
        return Http::ok($response, ['grades' => $this->grades->gradesForStudent((int) $user['id'])]);
    }

    private function createGradeItemOrGrade(Request $request, Response $response, array $user): Response
    {
        $data = Http::body($request);
        $action = (string) ($data['action'] ?? 'grade');
        if ($action === 'item') {
            return $this->createGradeItem($response, $user, $data);
        }
        if ($action !== 'grade') {
            return Http::validation($response, ['action' => 'action must be grade or item']);
        }
        $errors = $this->validator->required($data, ['grade_item_id', 'student_id']);
        if ($errors !== []) {
            return Http::validation($response, $errors);
        }
        $item = $this->grades->item((int) $data['grade_item_id']);
        if ($item === null) {
            return Http::notFound($response);
        }
        $course = $this->courses->find((int) $item['course_id']);
        if (!$this->cap->has($user, 'grade.manage.any') && !($this->cap->has($user, 'grade.manage.own') && $this->cap->owns($user, $course))) {
            return Http::deny($response);
        }
        $id = $this->grades->saveGrade([
            'grade_item_id' => (int) $item['id'],
            'student_id' => (int) $data['student_id'],
            'score' => (float) ($data['score'] ?? 0),
            'feedback' => (string) ($data['feedback'] ?? ''),
            'graded_by' => (int) $user['id'],
        ]);
        $this->system->audit(['user_id' => $user['id'], 'action' => 'grade.create', 'entity_type' => 'grades', 'entity_id' => $id, 'detail_json' => json_encode(['grade_item_id' => (int) $item['id']])]);
        return Http::created($response, ['grade' => $this->grades->gradeFor((int) $item['id'], (int) $data['student_id'])]);
    }

    private function createGradeItem(Response $response, array $user, array $data): Response
    {
        $errors = $this->validator->required($data, ['course_id', 'name']);
        if ($errors !== []) {
            return Http::validation($response, $errors);
        }
        $course = $this->courses->find((int) $data['course_id']);
        if ($course === null) {
            return Http::notFound($response);
        }
        if (!$this->cap->has($user, 'grade.manage.any') && !($this->cap->has($user, 'grade.manage.own') && $this->cap->owns($user, $course))) {
            return Http::deny($response);
        }
        $id = $this->grades->addItem([
            'course_id' => (int) $course['id'],
            'instructor_id' => (int) $user['id'],
            'name' => trim((string) $data['name']),
            'max_points' => (float) ($data['max_points'] ?? 100),
            'weight' => (float) ($data['weight'] ?? 1),
        ]);
        return Http::created($response, ['grade_item' => $this->grades->item($id)]);
    }

    private function updateGrade(Request $request, Response $response, array $user, int $id): Response
    {
        $grade = $this->db->first('SELECT g.*, gi.course_id, gi.name AS item_name FROM grades g JOIN grade_items gi ON gi.id = g.grade_item_id WHERE g.id = ?', [$id]);
        if ($grade === null) {
            return Http::notFound($response);
        }
        $course = $this->courses->find((int) $grade['course_id']);
        if (!$this->cap->has($user, 'grade.manage.any') && !($this->cap->has($user, 'grade.manage.own') && $this->cap->owns($user, $course))) {
            return Http::deny($response);
        }
        $data = Http::body($request);
        $update = [];
        if (isset($data['score'])) {
            $update['score'] = (float) $data['score'];
        }
        if (isset($data['feedback'])) {
            $update['feedback'] = (string) $data['feedback'];
        }
        if ($update === []) {
            return Http::validation($response, ['update' => 'No fields provided to update']);
        }
        $update['graded_by'] = (int) $user['id'];
        $this->db->update('grades', $update, 'id = :id', ['id' => $id]);
        $this->system->audit(['user_id' => $user['id'], 'action' => 'grade.update', 'entity_type' => 'grades', 'entity_id' => $id, 'detail_json' => json_encode(array_keys($update))]);
        return Http::ok($response, ['grade' => $this->db->first('SELECT * FROM grades WHERE id = ?', [$id])]);
    }

    // ---- LMS-10 Grade export ----

    private function listExports(Request $request, Response $response, array $user): Response
    {
        if (!$this->cap->has($user, 'grade.export.own') && !$this->cap->has($user, 'grade.export.any')) {
            return Http::deny($response, 'Only instructors and administrators can view grade exports');
        }
        $all = $this->cap->has($user, 'grade.export.any');
        return Http::ok($response, ['exports' => $this->system->exportsForUser((int) $user['id'], $all)]);
    }

    private function createExport(Request $request, Response $response, array $user): Response
    {
        $data = Http::body($request);
        $courseId = (int) ($data['course_id'] ?? 0);
        $format = (string) ($data['format'] ?? 'csv');
        if (!in_array($format, ['csv', 'pdf'], true)) {
            return Http::validation($response, ['format' => 'format must be csv or pdf']);
        }
        $course = $this->courses->find($courseId);
        if ($course === null) {
            return Http::notFound($response);
        }
        if (!$this->cap->has($user, 'grade.export.any') && !($this->cap->has($user, 'grade.export.own') && $this->cap->owns($user, $course))) {
            return Http::deny($response);
        }
        $gradebook = $this->grades->gradebookForCourse($courseId);
        $columns = ['username', 'display_name', 'item_name', 'score', 'max_points'];
        $rows = array_map(static fn ($g) => [
            'username' => $g['username'],
            'display_name' => $g['display_name'],
            'item_name' => $g['item_name'],
            'score' => $g['score'] === null ? '' : (string) $g['score'],
            'max_points' => $g['max_points'],
        ], $gradebook);
        if ($format === 'pdf') {
            $content = $this->exports->pdf($rows, $columns, 'Gradebook: ' . $course['title']);
            $extension = 'pdf';
        } else {
            $content = $this->exports->csv($rows, $columns);
            $extension = 'csv';
        }
        $file = $this->exports->write($content, $extension, 'grade-export-' . $courseId);
        $id = $this->system->addExport([
            'course_id' => $courseId,
            'requested_by' => (int) $user['id'],
            'format' => $format,
            'kind' => 'grades',
            'file_path' => $file['file_path'],
            'status' => 'ready',
        ]);
        $this->system->audit(['user_id' => $user['id'], 'action' => 'export.create', 'entity_type' => 'exports', 'entity_id' => $id, 'detail_json' => json_encode(['course_id' => $courseId, 'format' => $format])]);
        return Http::created($response, ['export' => $this->system->export($id)]);
    }

    private function regenerateExport(Request $request, Response $response, array $user, int $id): Response
    {
        $export = $this->system->export($id);
        if ($export === null) {
            return Http::notFound($response);
        }
        $courseId = (int) ($export['course_id'] ?? 0);
        $course = $courseId > 0 ? $this->courses->find($courseId) : null;
        if (!$this->cap->has($user, 'grade.export.any') && !($this->cap->has($user, 'grade.export.own') && $this->cap->owns($user, $course))) {
            return Http::deny($response);
        }
        $format = (string) ($export['format'] ?? 'csv');
        $gradebook = $course !== null ? $this->grades->gradebookForCourse($courseId) : [];
        $columns = ['username', 'display_name', 'item_name', 'score', 'max_points'];
        $rows = array_map(static fn ($g) => [
            'username' => $g['username'],
            'display_name' => $g['display_name'],
            'item_name' => $g['item_name'],
            'score' => $g['score'] === null ? '' : (string) $g['score'],
            'max_points' => $g['max_points'],
        ], $gradebook);
        $content = $format === 'pdf' ? $this->exports->pdf($rows, $columns, 'Gradebook') : $this->exports->csv($rows, $columns);
        $file = $this->exports->write($content, $format, 'grade-export-' . $courseId);
        $this->db->update('exports', ['file_path' => $file['file_path'], 'status' => 'ready', 'created_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $id]);
        return Http::ok($response, ['export' => $this->system->export($id)]);
    }

    // ---- LMS-11 Bulk course report ----

    private function listReports(Request $request, Response $response, array $user): Response
    {
        if (!$this->cap->has($user, 'report.bulk')) {
            return Http::deny($response);
        }
        $filters = $request->getQueryParams();
        $report = null;
        if (isset($filters['run']) && $filters['run'] === '1') {
            $report = $this->runReport((int) $user['id'], $filters);
        }
        return Http::ok($response, ['reports' => $this->system->reports(), 'live' => $report]);
    }

    private function createReport(Request $request, Response $response, array $user): Response
    {
        if (!$this->cap->has($user, 'report.bulk')) {
            return Http::deny($response);
        }
        $data = Http::body($request);
        $report = $this->runReport((int) $user['id'], $data);
        $this->system->audit(['user_id' => $user['id'], 'action' => 'report.create', 'entity_type' => 'reports', 'entity_id' => $report['id'], 'detail_json' => json_encode(['report_type' => $report['report_type']])]);
        return Http::created($response, ['report' => $report]);
    }

    private function regenerateReport(Request $request, Response $response, array $user, int $id): Response
    {
        if (!$this->cap->has($user, 'report.bulk')) {
            return Http::deny($response);
        }
        $existing = $this->system->report($id);
        if ($existing === null) {
            return Http::notFound($response);
        }
        $filters = json_decode($existing['filters_json'], true) ?: [];
        $data = Http::body($request);
        if ($data !== []) {
            $filters = $data;
        }
        $report = $this->runReport((int) $user['id'], $filters, $id);
        return Http::ok($response, ['report' => $report]);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function runReport(int $userId, array $filters, ?int $existingId = null): array
    {
        $stats = $this->system->bulkStats($filters);
        if ($existingId !== null) {
            $this->db->update('reports', [
                'filters_json' => (string) json_encode($filters),
                'summary_json' => (string) json_encode($stats),
                'status' => 'ready',
                'created_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => $existingId]);
            return $this->system->report($existingId);
        }
        $id = $this->system->addReport([
            'requested_by' => $userId,
            'report_type' => (string) ($filters['report_type'] ?? 'courses'),
            'filters_json' => (string) json_encode($filters),
            'summary_json' => (string) json_encode($stats),
            'status' => 'ready',
        ]);
        return $this->system->report($id);
    }

    // ---- LMS-12 Administrative API ----

    private function listAdmin(Request $request, Response $response, array $user): Response
    {
        if (!$this->cap->has($user, 'admin.api')) {
            return Http::deny($response);
        }
        $entity = (string) ($request->getQueryParams()['entity'] ?? 'users');
        return match ($entity) {
            'users' => Http::ok($response, ['entity' => 'users', 'users' => $this->users->all((string) ($request->getQueryParams()['role'] ?? ''), (string) ($request->getQueryParams()['q'] ?? ''))]),
            'courses' => Http::ok($response, ['entity' => 'courses', 'courses' => $this->courses->discover(['status' => 'all', 'visibility' => 'all'], true)]),
            'enrollments' => Http::ok($response, ['entity' => 'enrollments', 'enrollments' => $this->courses->allEnrollments((string) ($request->getQueryParams()['status'] ?? ''))]),
            'settings' => Http::ok($response, ['entity' => 'settings', 'settings' => $this->system->allSettings()]),
            'audit' => Http::ok($response, ['entity' => 'audit', 'audit_events' => $this->system->auditEvents()]),
            'roles' => Http::ok($response, ['entity' => 'roles', 'roles' => $this->db->select('SELECT * FROM roles ORDER BY id')]),
            default => Http::error($response, 400, 'BAD_REQUEST', 'entity must be users, courses, enrollments, settings, audit or roles'),
        };
    }

    private function createAdminAction(Request $request, Response $response, array $user): Response
    {
        if (!$this->cap->has($user, 'admin.api')) {
            return Http::deny($response);
        }
        $data = Http::body($request);
        $action = (string) ($data['action'] ?? '');
        return match ($action) {
            'user' => $this->adminCreateUser($response, $user, $data),
            'course' => $this->createCourse($request, $response, $user),
            'setting' => $this->adminSetSetting($response, $user, $data),
            'enroll' => $this->adminEnroll($response, $user, $data),
            default => Http::validation($response, ['action' => 'action must be user, course, setting or enroll']),
        };
    }

    private function adminCreateUser(Response $response, array $user, array $data): Response
    {
        $errors = $this->validator->validate($data, [
            'username' => ['required', 'min:3'],
            'email' => ['required', 'email'],
            'password' => ['required', 'min:8'],
            'display_name' => ['required', 'min:2'],
            'role' => ['required', 'in:visitor,student,instructor,admin'],
        ]);
        if ($errors !== []) {
            return Http::validation($response, $errors);
        }
        $roleId = $this->users->roleIdByName((string) $data['role']);
        if ($roleId === null) {
            return Http::validation($response, ['role' => 'Unknown role']);
        }
        $existing = $this->users->findByIdentifier((string) $data['username']);
        if ($existing !== null) {
            return Http::validation($response, ['username' => 'A user with that username or email already exists']);
        }
        $id = $this->users->create([
            'username' => (string) $data['username'],
            'email' => (string) $data['email'],
            'password_hash' => password_hash((string) $data['password'], PASSWORD_DEFAULT),
            'display_name' => (string) $data['display_name'],
            'role_id' => $roleId,
            'active' => isset($data['active']) ? ((int) $data['active'] === 1 ? 1 : 0) : 1,
        ]);
        $this->system->audit(['user_id' => $user['id'], 'action' => 'admin.user.create', 'entity_type' => 'users', 'entity_id' => $id, 'detail_json' => json_encode(['username' => $data['username'], 'role' => $data['role']])]);
        return Http::created($response, ['user' => $this->users->find($id)]);
    }

    private function adminSetSetting(Response $response, array $user, array $data): Response
    {
        $errors = $this->validator->required($data, ['key', 'value']);
        if ($errors !== []) {
            return Http::validation($response, $errors);
        }
        $this->system->setSetting((string) $data['key'], (string) $data['value']);
        $this->system->audit(['user_id' => $user['id'], 'action' => 'admin.setting.set', 'entity_type' => 'settings', 'entity_id' => 0, 'detail_json' => json_encode(['key' => $data['key']])]);
        return Http::ok($response, ['setting' => ['key' => $data['key'], 'value' => $this->system->setting((string) $data['key'])]]);
    }

    private function adminEnroll(Response $response, array $user, array $data): Response
    {
        $errors = $this->validator->required($data, ['course_id', 'user_id']);
        if ($errors !== []) {
            return Http::validation($response, $errors);
        }
        $course = $this->courses->find((int) $data['course_id']);
        if ($course === null) {
            return Http::notFound($response);
        }
        $this->courses->enroll((int) $course['id'], (int) $data['user_id'], (string) ($data['status'] ?? 'enrolled'));
        $this->system->audit(['user_id' => $user['id'], 'action' => 'admin.enrollment.set', 'entity_type' => 'enrollments', 'entity_id' => 0, 'detail_json' => json_encode($data)]);
        return Http::ok($response, ['enrolled' => true]);
    }

    private function updateAdminAction(Request $request, Response $response, array $user, int $id): Response
    {
        if (!$this->cap->has($user, 'admin.api')) {
            return Http::deny($response);
        }
        $data = Http::body($request);
        $entity = (string) ($data['entity'] ?? 'user');
        if ($entity === 'user') {
            $target = $this->users->find($id);
            if ($target === null) {
                return Http::notFound($response);
            }
            if ($id === (int) $user['id'] && ($data['role'] ?? '') !== '' && (string) $data['role'] !== 'admin') {
                return Http::error($response, 409, 'ROLE_PROTECTED', 'You cannot demote your own admin role');
            }
            $update = [];
            if (isset($data['role'])) {
                $roleId = $this->users->roleIdByName((string) $data['role']);
                if ($roleId === null) {
                    return Http::validation($response, ['role' => 'Unknown role']);
                }
                $update['role_id'] = $roleId;
            }
            if (isset($data['active'])) {
                $update['active'] = (int) $data['active'] === 1 ? 1 : 0;
            }
            if (isset($data['display_name'])) {
                $update['display_name'] = (string) $data['display_name'];
            }
            if ($update === []) {
                return Http::validation($response, ['update' => 'No fields provided to update']);
            }
            $this->users->update($id, $update);
            $this->system->audit(['user_id' => $user['id'], 'action' => 'admin.user.update', 'entity_type' => 'users', 'entity_id' => $id, 'detail_json' => json_encode(array_keys($update))]);
            return Http::ok($response, ['user' => $this->users->find($id)]);
        }
        if ($entity === 'setting') {
            $row = $this->db->first('SELECT key FROM settings WHERE key = ?', [(string) $id]);
            if ($row === null) {
                return Http::notFound($response);
            }
            $this->system->setSetting((string) $id, (string) ($data['value'] ?? ''));
            $this->system->audit(['user_id' => $user['id'], 'action' => 'admin.setting.update', 'entity_type' => 'settings', 'entity_id' => 0, 'detail_json' => json_encode(['key' => (string) $id])]);
            return Http::ok($response, ['setting' => ['key' => (string) $id, 'value' => $this->system->setting((string) $id)]]);
        }
        return Http::error($response, 400, 'BAD_REQUEST', 'entity must be user or setting');
    }

    // ---- LMS-13 Frontend API integration ----

    private function listFrontendIntegration(Request $request, Response $response, array $user): Response
    {
        $states = ['loading', 'empty', 'success', 'validation_error', 'forbidden', 'not_found'];
        return Http::ok($response, [
            'states' => $states,
            'hints' => [
                'The UI loads all module data through the JSON API.',
                'Loading and empty states are rendered while the request is in flight.',
                'Errors are rendered from the stable {ok:false, error:{code,message}} payload.',
            ],
            'endpoints' => array_map(static fn ($m) => [
                'module' => $m,
                'get' => '/api/lms/' . $m,
                'post' => '/api/lms/' . $m,
                'patch' => '/api/lms/' . $m . '/{id}',
            ], self::MODULES),
        ]);
    }

    private function createFrontendIntegration(Request $request, Response $response, array $user): Response
    {
        $data = Http::body($request);
        $state = (string) ($data['state'] ?? 'success');
        if ($state === 'validation_error') {
            return Http::validation($response, ['state' => 'A synthetic validation error was requested']);
        }
        if ($state === 'forbidden') {
            return Http::deny($response, 'A synthetic forbidden error was requested');
        }
        if ($state === 'not_found') {
            return Http::notFound($response, 'A synthetic not-found error was requested');
        }
        return Http::ok($response, ['echoed' => $data, 'state' => $state]);
    }

    private function updateFrontendIntegration(Request $request, Response $response, array $user, int $id): Response
    {
        $data = Http::body($request);
        return Http::ok($response, ['echoed' => $data, 'id' => $id, 'updated' => true]);
    }

    // ---- LMS-14 Error responses ----

    private function listErrorResponses(Request $request, Response $response, array $user): Response
    {
        $code = (string) ($request->getQueryParams()['code'] ?? '');
        $known = [
            'VALIDATION_ERROR' => [422, 'Validation failed'],
            'UNAUTHENTICATED' => [401, 'Authentication required'],
            'FORBIDDEN' => [403, 'You are not authorized to perform this action'],
            'NOT_FOUND' => [404, 'Record not found'],
            'INTERNAL_ERROR' => [500, 'An unexpected error occurred. Please try again later.'],
        ];
        if ($code !== '' && isset($known[$code])) {
            [$status, $message] = $known[$code];
            return Http::error($response, $status, $code, $message);
        }
        return Http::ok($response, [
            'known_codes' => array_keys($known),
            'contract' => 'Errors always use the shape {"ok":false,"error":{"code":"...","message":"..."}} and never expose internal stack traces.',
        ]);
    }

    private function createErrorResponses(Request $request, Response $response, array $user): Response
    {
        $data = Http::body($request);
        $code = (string) ($data['code'] ?? '');
        if ($code === '' || !in_array($code, ['VALIDATION_ERROR', 'UNAUTHENTICATED', 'FORBIDDEN', 'NOT_FOUND', 'INTERNAL_ERROR'], true)) {
            return Http::validation($response, ['code' => 'code must be one of VALIDATION_ERROR, UNAUTHENTICATED, FORBIDDEN, NOT_FOUND, INTERNAL_ERROR']);
        }
        return Http::error($response, match ($code) {
            'VALIDATION_ERROR' => 422,
            'UNAUTHENTICATED' => 401,
            'FORBIDDEN' => 403,
            'NOT_FOUND' => 404,
            default => 500,
        }, $code, 'Requested stable error response: ' . $code);
    }

    private function updateErrorResponses(Request $request, Response $response, array $user, int $id): Response
    {
        // No editable records exist for this module; PATCH always returns a
        // stable 404, which is itself part of the error response contract.
        return Http::notFound($response, 'Error response records cannot be modified');
    }

    // ---- Shared helpers ----

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $course
     */
    private function memberOf(array $user, array $course): bool
    {
        if ($this->cap->isAdmin($user)) {
            return true;
        }
        if ($this->cap->isInstructor($user)) {
            return $this->cap->owns($user, $course);
        }
        return $this->courses->isEnrolled((int) $course['id'], (int) $user['id']);
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function publicUser(array $user): array
    {
        return [
            'id' => (int) $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'display_name' => $user['display_name'],
            'role' => $user['role_name'],
        ];
    }

    /**
     * @param array<string, mixed> $course
     * @return array<string, mixed>
     */
    private function publicCourse(array $course, int $userId): array
    {
        return [
            'id' => (int) $course['id'],
            'title' => $course['title'],
            'category' => $course['category'],
            'semester' => $course['semester'],
            'instructor_name' => $course['instructor_name'],
            'description' => $course['description'],
            'visibility' => $course['visibility'],
            'status' => $course['status'],
            'enrollment_count' => $course['enrollment_count'] ?? 0,
            'enrolled' => $this->courses->isEnrolled((int) $course['id'], $userId),
        ];
    }
}
