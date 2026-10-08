<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config;
use App\Http;
use App\Repositories\AssignmentRepository;
use App\Repositories\CourseRepository;
use App\Repositories\GradeRepository;
use App\Repositories\QuizRepository;
use App\Repositories\SystemRepository;
use App\Repositories\UserRepository;
use App\Services\CapabilityService;
use App\Services\ExportService;
use App\Services\FileService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Server-rendered browser pages for every actor-facing workflow. Pages render
 * a shell and populate dynamic data through the JSON API so loading, empty,
 * and error states are handled consistently by the frontend (LMS-13).
 */
final class PageController
{
    public function __construct(
        private Config $config,
        private \App\Services\AuthService $auth,
        private CapabilityService $cap,
        private CourseRepository $courses,
        private AssignmentRepository $assignments,
        private QuizRepository $quizzes,
        private GradeRepository $grades,
        private SystemRepository $system,
        private UserRepository $users,
        private FileService $files,
        private ExportService $exports,
    ) {
    }

    private function guest(Request $request, Response $response): ?Response
    {
        if ($request->getAttribute('user') === null) {
            return $response->withHeader('Location', '/login')->withStatus(302);
        }
        return null;
    }

    public function dashboard(Request $request, Response $response): Response
    {
        $redirect = $this->guest($request, $response);
        if ($redirect !== null) {
            return $redirect;
        }
        $user = $request->getAttribute('user');
        $role = $user['role_name'];
        $data = ['pageTitle' => 'Dashboard', 'active' => 'dashboard', 'user' => $user];

        if ($role === 'student' || $role === 'visitor') {
            $data['enrolled_courses'] = $this->courses->enrollmentsForUser((int) $user['id']);
            $data['announcements'] = $this->courses->announcementsForUser((int) $user['id']);
            $data['grades'] = $this->grades->gradesForStudent((int) $user['id']);
        } elseif ($role === 'instructor') {
            $data['courses'] = $this->courses->discover(['status' => 'all', 'visibility' => 'all', 'instructor_id' => (string) $user['id']], true);
            $pending = 0;
            foreach ($data['courses'] as $course) {
                foreach ($this->assignments->assignmentsForCourse((int) $course['id']) as $assignment) {
                    foreach ($this->assignments->submissionsForAssignment((int) $assignment['id']) as $submission) {
                        if ($submission['status'] === 'submitted') {
                            $pending++;
                        }
                    }
                }
            }
            $data['pending_submissions'] = $pending;
        } else {
            $data['stats'] = $this->system->bulkStats();
            $data['recent_audit'] = $this->system->auditEvents(10);
        }
        return Http::view($response, $this->config, 'dashboard', $data);
    }

    public function courses(Request $request, Response $response): Response
    {
        $redirect = $this->guest($request, $response);
        if ($redirect !== null) {
            return $redirect;
        }
        $user = $request->getAttribute('user');
        return Http::view($response, $this->config, 'courses', [
            'pageTitle' => 'Course Discovery',
            'active' => 'courses',
            'user' => $user,
        ]);
    }

    public function courseDetail(Request $request, Response $response, array $args): Response
    {
        $redirect = $this->guest($request, $response);
        if ($redirect !== null) {
            return $redirect;
        }
        $course = $this->courses->find((int) $args['id']);
        if ($course === null) {
            return Http::view($response, $this->config, 'error_page', ['pageTitle' => 'Not Found', 'message' => 'The course you requested does not exist.']);
        }
        $user = $request->getAttribute('user');
        if (!$this->memberOf($user, $course)) {
            return Http::view($response, $this->config, 'error_page', ['pageTitle' => 'Access Denied', 'message' => 'You are not a member of this course.']);
        }
        return Http::view($response, $this->config, 'course_detail', [
            'pageTitle' => $course['title'],
            'active' => 'courses',
            'user' => $user,
            'course' => $course,
        ]);
    }

    public function courseDiscussion(Request $request, Response $response, array $args): Response
    {
        $redirect = $this->guest($request, $response);
        if ($redirect !== null) {
            return $redirect;
        }
        $course = $this->courses->find((int) $args['id']);
        if ($course === null) {
            return Http::view($response, $this->config, 'error_page', ['pageTitle' => 'Not Found', 'message' => 'The course you requested does not exist.']);
        }
        $user = $request->getAttribute('user');
        if (!$this->memberOf($user, $course)) {
            return Http::view($response, $this->config, 'error_page', ['pageTitle' => 'Access Denied', 'message' => 'You are not a member of this course.']);
        }
        return Http::view($response, $this->config, 'discussion', ['pageTitle' => $course['title'] . ' - Discussion', 'active' => 'courses', 'user' => $user, 'course' => $course]);
    }

    public function courseAssignments(Request $request, Response $response, array $args): Response
    {
        $redirect = $this->guest($request, $response);
        if ($redirect !== null) {
            return $redirect;
        }
        $course = $this->courses->find((int) $args['id']);
        if ($course === null) {
            return Http::view($response, $this->config, 'error_page', ['pageTitle' => 'Not Found', 'message' => 'The course you requested does not exist.']);
        }
        $user = $request->getAttribute('user');
        if (!$this->memberOf($user, $course)) {
            return Http::view($response, $this->config, 'error_page', ['pageTitle' => 'Access Denied', 'message' => 'You are not a member of this course.']);
        }
        return Http::view($response, $this->config, 'assignments', ['pageTitle' => $course['title'] . ' - Assignments', 'active' => 'courses', 'user' => $user, 'course' => $course]);
    }

    public function courseQuizzes(Request $request, Response $response, array $args): Response
    {
        $redirect = $this->guest($request, $response);
        if ($redirect !== null) {
            return $redirect;
        }
        $course = $this->courses->find((int) $args['id']);
        if ($course === null) {
            return Http::view($response, $this->config, 'error_page', ['pageTitle' => 'Not Found', 'message' => 'The course you requested does not exist.']);
        }
        $user = $request->getAttribute('user');
        if (!$this->memberOf($user, $course)) {
            return Http::view($response, $this->config, 'error_page', ['pageTitle' => 'Access Denied', 'message' => 'You are not a member of this course.']);
        }
        return Http::view($response, $this->config, 'quizzes', ['pageTitle' => $course['title'] . ' - Quizzes', 'active' => 'courses', 'user' => $user, 'course' => $course]);
    }

    public function courseGradebook(Request $request, Response $response, array $args): Response
    {
        $redirect = $this->guest($request, $response);
        if ($redirect !== null) {
            return $redirect;
        }
        $course = $this->courses->find((int) $args['id']);
        if ($course === null) {
            return Http::view($response, $this->config, 'error_page', ['pageTitle' => 'Not Found', 'message' => 'The course you requested does not exist.']);
        }
        $user = $request->getAttribute('user');
        if (!$this->cap->isAdmin($user) && !($this->cap->isInstructor($user) && $this->cap->owns($user, $course))) {
            return Http::view($response, $this->config, 'error_page', ['pageTitle' => 'Access Denied', 'message' => 'Only the course instructor can view the gradebook.']);
        }
        return Http::view($response, $this->config, 'gradebook', ['pageTitle' => $course['title'] . ' - Gradebook', 'active' => 'courses', 'user' => $user, 'course' => $course]);
    }

    public function quizTake(Request $request, Response $response, array $args): Response
    {
        $redirect = $this->guest($request, $response);
        if ($redirect !== null) {
            return $redirect;
        }
        $quiz = $this->quizzes->quiz((int) $args['id']);
        if ($quiz === null) {
            return Http::view($response, $this->config, 'error_page', ['pageTitle' => 'Not Found', 'message' => 'The quiz you requested does not exist.']);
        }
        $user = $request->getAttribute('user');
        $course = $this->courses->find((int) $quiz['course_id']);
        if (!$this->memberOf($user, $course)) {
            return Http::view($response, $this->config, 'error_page', ['pageTitle' => 'Access Denied', 'message' => 'You are not a member of this course.']);
        }
        if (($user['role_name'] ?? '') === 'student') {
            $existing = $this->quizzes->latestAttempt((int) $quiz['id'], (int) $user['id']);
            if ($existing !== null && $existing['status'] === 'completed') {
                return $response->withHeader('Location', '/quizzes/' . $quiz['id'] . '/results')->withStatus(302);
            }
        }
        return Http::view($response, $this->config, 'quiz_take', ['pageTitle' => $quiz['title'], 'active' => 'courses', 'user' => $user, 'quiz' => $quiz, 'course' => $course]);
    }

    public function quizResults(Request $request, Response $response, array $args): Response
    {
        $redirect = $this->guest($request, $response);
        if ($redirect !== null) {
            return $redirect;
        }
        $quiz = $this->quizzes->quiz((int) $args['id']);
        if ($quiz === null) {
            return Http::view($response, $this->config, 'error_page', ['pageTitle' => 'Not Found', 'message' => 'The quiz you requested does not exist.']);
        }
        $user = $request->getAttribute('user');
        $course = $this->courses->find((int) $quiz['course_id']);
        $isManager = $this->cap->isAdmin($user) || ($this->cap->isInstructor($user) && $this->cap->owns($user, $course));
        if (!$isManager) {
            if (!$this->memberOf($user, $course)) {
                return Http::view($response, $this->config, 'error_page', ['pageTitle' => 'Access Denied', 'message' => 'You are not a member of this course.']);
            }
            $attempt = $this->quizzes->latestAttempt((int) $quiz['id'], (int) $user['id']);
            if ($attempt === null || $attempt['status'] !== 'completed') {
                return $response->withHeader('Location', '/quizzes/' . $quiz['id'] . '/take')->withStatus(302);
            }
        }
        return Http::view($response, $this->config, 'quiz_results', ['pageTitle' => $quiz['title'] . ' - Results', 'active' => 'courses', 'user' => $user, 'quiz' => $quiz, 'course' => $course]);
    }

    public function myGrades(Request $request, Response $response): Response
    {
        $redirect = $this->guest($request, $response);
        if ($redirect !== null) {
            return $redirect;
        }
        $user = $request->getAttribute('user');
        return Http::view($response, $this->config, 'grades', ['pageTitle' => 'My Grades', 'active' => 'grades', 'user' => $user]);
    }

    public function exports(Request $request, Response $response): Response
    {
        $redirect = $this->guest($request, $response);
        if ($redirect !== null) {
            return $redirect;
        }
        $user = $request->getAttribute('user');
        if (!$this->cap->has($user, 'grade.export.own') && !$this->cap->has($user, 'grade.export.any')) {
            return Http::view($response, $this->config, 'error_page', ['pageTitle' => 'Access Denied', 'message' => 'Only instructors and administrators can export grades.']);
        }
        return Http::view($response, $this->config, 'exports', ['pageTitle' => 'Grade Exports', 'active' => 'exports', 'user' => $user]);
    }

    public function adminDashboard(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        return Http::view($response, $this->config, 'admin_dashboard', ['pageTitle' => 'Administration', 'active' => 'admin', 'user' => $user]);
    }

    public function adminReports(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        return Http::view($response, $this->config, 'admin_reports', ['pageTitle' => 'Bulk Course Report', 'active' => 'admin', 'user' => $user]);
    }

    public function apiIntegration(Request $request, Response $response): Response
    {
        $redirect = $this->guest($request, $response);
        if ($redirect !== null) {
            return $redirect;
        }
        $user = $request->getAttribute('user');
        return Http::view($response, $this->config, 'api_integration', ['pageTitle' => 'Frontend API Integration', 'active' => 'api', 'user' => $user]);
    }

    public function errorDemo(Request $request, Response $response): Response
    {
        $redirect = $this->guest($request, $response);
        if ($redirect !== null) {
            return $redirect;
        }
        $user = $request->getAttribute('user');
        return Http::view($response, $this->config, 'error_demo', ['pageTitle' => 'Error Responses', 'active' => 'api', 'user' => $user]);
    }

    public function downloadMaterial(Request $request, Response $response, array $args): Response
    {
        $redirect = $this->guest($request, $response);
        if ($redirect !== null) {
            return $redirect;
        }
        $material = $this->courses->material((int) $args['id']);
        if ($material === null) {
            return Http::view($response, $this->config, 'error_page', ['pageTitle' => 'Not Found', 'message' => 'The requested material does not exist.']);
        }
        $course = $this->courses->find((int) $material['course_id']);
        $user = $request->getAttribute('user');
        if (!$this->memberOf($user, $course)) {
            return Http::view($response, $this->config, 'error_page', ['pageTitle' => 'Access Denied', 'message' => 'You are not a member of this course.']);
        }
        return $this->streamFile($response, $this->files->absolutePath((string) $material['stored_path']), $material['filename'], $material['mime_type']);
    }

    public function downloadSubmission(Request $request, Response $response, array $args): Response
    {
        $redirect = $this->guest($request, $response);
        if ($redirect !== null) {
            return $redirect;
        }
        $submission = $this->assignments->submission((int) $args['id']);
        if ($submission === null) {
            return Http::view($response, $this->config, 'error_page', ['pageTitle' => 'Not Found', 'message' => 'The requested submission does not exist.']);
        }
        $user = $request->getAttribute('user');
        $course = $this->courses->find((int) $submission['course_id']);
        $isManager = $this->cap->isAdmin($user) || ($this->cap->isInstructor($user) && $this->cap->owns($user, $course));
        if (!($isManager || (int) $submission['id'] === (int) $user['id'])) {
            return Http::view($response, $this->config, 'error_page', ['pageTitle' => 'Access Denied', 'message' => 'You are not authorized to download this submission.']);
        }
        return $this->streamFile($response, $this->files->absolutePath((string) $submission['stored_path']), $submission['filename'], $submission['mime_type']);
    }

    public function downloadExport(Request $request, Response $response, array $args): Response
    {
        $export = $this->system->export((int) $args['id']);
        if ($export === null) {
            return Http::view($response, $this->config, 'error_page', ['pageTitle' => 'Not Found', 'message' => 'The requested export does not exist.']);
        }
        $user = $request->getAttribute('user');
        $canAccess = $this->cap->has($user, 'grade.export.any')
            || ($this->cap->has($user, 'grade.export.own') && (int) $export['requested_by'] === (int) $user['id']);
        if (!$canAccess) {
            return Http::view($response, $this->config, 'error_page', ['pageTitle' => 'Access Denied', 'message' => 'You are not authorized to download this export.']);
        }
        $mime = ($export['format'] ?? 'csv') === 'pdf' ? 'application/pdf' : 'text/csv';
        return $this->streamFile($response, $this->exports->filePath((string) $export['file_path']), (string) $export['file_path'], $mime);
    }

    private function streamFile(Response $response, string $path, string $downloadName, string $mime): Response
    {
        if (!is_file($path)) {
            return Http::view($response, $this->config, 'error_page', ['pageTitle' => 'Not Found', 'message' => 'The stored file could not be located.']);
        }
        $response->getBody()->write((string) file_get_contents($path));
        return $response
            ->withHeader('Content-Type', $mime)
            ->withHeader('Content-Disposition', 'attachment; filename="' . addslashes($downloadName) . '"')
            ->withHeader('Content-Length', (string) filesize($path));
    }

    /**
     * @param array<string, mixed>|null $user
     * @param array<string, mixed>|null $course
     */
    private function memberOf(?array $user, ?array $course): bool
    {
        if ($user === null || $course === null) {
            return false;
        }
        if ($this->cap->isAdmin($user)) {
            return true;
        }
        if ($this->cap->isInstructor($user)) {
            return $this->cap->owns($user, $course);
        }
        return $this->courses->isEnrolled((int) $course['id'], (int) $user['id']);
    }
}
