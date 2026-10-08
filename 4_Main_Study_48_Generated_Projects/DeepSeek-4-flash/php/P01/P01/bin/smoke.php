<?php

declare(strict_types=1);

/**
 * In-process smoke test. Boots the Slim application and dispatches a series
 * of HTTP requests without opening a network port, verifying every use-case
 * module responds with the expected status codes.
 *
 * Usage: php bin/smoke.php [--verbose]
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Slim\Psr7\Factory\ServerRequestFactory;

$verbose = in_array('--verbose', $argv ?? [], true);
$root = dirname(__DIR__);
$config = new App\Config($root);
$app = App\App::create($config);

function makeRequest(string $method, string $path, array $body = [], array $query = [], ?string $cookie = null, array $files = []): array
{
    $uri = 'http://localhost' . $path . ($query !== [] ? '?' . http_build_query($query) : '');
    $request = (new ServerRequestFactory())->createServerRequest($method, $uri);
    if ($body !== []) {
        $request = $request->withHeader('Content-Type', 'application/json');
        $request->getBody()->write((string) json_encode($body));
        $request->getBody()->rewind();
    }
    if ($cookie !== null) {
        $request = $request->withHeader('Cookie', App\Services\AuthService::COOKIE . '=' . $cookie);
    }
    if ($files !== []) {
        $request = $request->withUploadedFiles($files);
    }
    return [$request, $uri];
}

function dispatch(\Slim\App $app, string $method, string $path, array $body = [], array $query = [], ?string $cookie = null, array $files = []): array
{
    [$request, $uri] = makeRequest($method, $path, $body, $query, $cookie, $files);
    $response = $app->handle($request);
    $payload = json_decode((string) $response->getBody(), true);
    return ['status' => $response->getStatusCode(), 'uri' => $uri, 'payload' => $payload];
}

$results = [];
$check = function (string $label, int $status, string $method, string $path, array $body = [], array $query = [], ?string $cookie = null, array $files = []) use (&$results, $app, $verbose) {
    $r = dispatch($app, $method, $path, $body, $query, $cookie, $files);
    $ok = $r['status'] === $status;
    $results[] = ['label' => $label, 'ok' => $ok, 'status' => $r['status'], 'expected' => $status, 'uri' => $r['uri'], 'payload' => $r['payload']];
    if (!$ok && $verbose) {
        echo "[FAIL] {$label}: got {$r['status']}, expected {$status}\n";
    }
};

echo "Running P01 LMS smoke checks...\n";

function sessionCookie(\Slim\App $app, string $identifier, string $password): ?string
{
    [$request] = makeRequest('POST', '/api/lms/account_access', ['action' => 'login', 'identifier' => $identifier, 'password' => $password]);
    $response = $app->handle($request);
    foreach ($response->getHeader('Set-Cookie') as $c) {
        if (str_starts_with($c, 'lms_session=')) {
            return substr($c, strlen('lms_session='), (int) strpos($c, ';') - strlen('lms_session='));
        }
    }
    return null;
}

// ---- Authentication (LMS-01) ----
$cookie = sessionCookie($app, 'student1', 'StudentPass123!');
$check('LMS-01 login sets session', 200, 'GET', '/api/me', [], [], $cookie);
$check('LMS-01 invalid login rejected (422)', 422, 'POST', '/api/lms/account_access', ['action' => 'login', 'identifier' => 'student1', 'password' => 'wrong']);

// ---- Unauthenticated guard ----
$check('LMS-01 API guard (401)', 401, 'GET', '/api/lms/course_discovery');

// ---- LMS-02 Course discovery (student) ----
$check('LMS-02 course list (200)', 200, 'GET', '/api/lms/course_discovery', [], [], $cookie);
$check('LMS-02 filtered search (200)', 200, 'GET', '/api/lms/course_discovery', [], ['category' => 'Computer Science'], $cookie);

// ---- LMS-03 Enrollment (student) ----
$check('LMS-03 enroll (201)', 201, 'POST', '/api/lms/enrollment', ['course_id' => 3], [], $cookie);

// ---- LMS-04 Course materials (student member) ----
$check('LMS-04 list materials (200)', 200, 'GET', '/api/lms/course_materials', [], ['course_id' => 1], $cookie);
$check('LMS-04 non-member denied (403)', 403, 'GET', '/api/lms/course_materials', [], ['course_id' => 6], $cookie);

// ---- LMS-05 Announcements ----
$check('LMS-05 list announcements (200)', 200, 'GET', '/api/lms/announcements', [], ['course_id' => 1], $cookie);
$check('LMS-05 student cannot publish (403)', 403, 'POST', '/api/lms/announcements', ['course_id' => 1, 'title' => 'x'], [], $cookie);

// ---- LMS-06 Discussion board ----
$check('LMS-06 list threads (200)', 200, 'GET', '/api/lms/discussion_board', [], ['course_id' => 1], $cookie);
$check('LMS-06 post reply (201)', 201, 'POST', '/api/lms/discussion_board', ['course_id' => 1, 'subject' => 'Smoke test thread', 'body' => 'hello'], [], $cookie);

// ---- LMS-07 Assignment submission (student) ----
$check('LMS-07 list assignments (200)', 200, 'GET', '/api/lms/assignment_submission', [], ['course_id' => 1], $cookie);
$check('LMS-07 student cannot create assignment (403)', 403, 'POST', '/api/lms/assignment_submission', ['action' => 'create', 'course_id' => 1, 'title' => 'x'], [], $cookie);

// ---- LMS-08 Quiz lifecycle (student) ----
$check('LMS-08 list quizzes (200)', 200, 'GET', '/api/lms/quiz_lifecycle', [], ['course_id' => 1], $cookie);
$check('LMS-08 student cannot create quiz (403)', 403, 'POST', '/api/lms/quiz_lifecycle', ['action' => 'quiz', 'course_id' => 1, 'title' => 'x'], [], $cookie);

// ---- LMS-09 Grades (student) ----
$check('LMS-09 own grades (200)', 200, 'GET', '/api/lms/grades', [], [], $cookie);
$check('LMS-09 student cannot view gradebook (403)', 403, 'GET', '/api/lms/grades', [], ['course_id' => 1], $cookie);

// ---- LMS-10 Grade export (student denied) ----
$check('LMS-10 export list denied (403)', 403, 'GET', '/api/lms/grade_export', [], [], $cookie);

// ---- Instructor login ----
$instructorCookie = sessionCookie($app, 'alice', 'InstructorPass123!');
$tmpFile = tempnam(sys_get_temp_dir(), 'lms') . '.txt';
file_put_contents($tmpFile, "Smoke test material content\n");
$upload = new \Slim\Psr7\UploadedFile($tmpFile, 'smoke-material.txt', 'text/plain', filesize($tmpFile), UPLOAD_ERR_OK);
$check('LMS-04 instructor uploads material with file (201)', 201, 'POST', '/api/lms/course_materials', ['course_id' => 1, 'title' => 'Smoke material'], [], $instructorCookie, ['file' => $upload]);
$check('LMS-05 instructor publishes announcement (201)', 201, 'POST', '/api/lms/announcements', ['course_id' => 1, 'title' => 'Smoke announcement'], [], $instructorCookie);
$check('LMS-07 instructor creates assignment (201)', 201, 'POST', '/api/lms/assignment_submission', ['action' => 'create', 'course_id' => 1, 'title' => 'Smoke assignment', 'due_at' => '2026-12-31 23:59:59'], [], $instructorCookie);
$check('LMS-08 instructor creates quiz (201)', 201, 'POST', '/api/lms/quiz_lifecycle', ['action' => 'quiz', 'course_id' => 1, 'title' => 'Smoke quiz'], [], $instructorCookie);
$check('LMS-09 instructor creates grade item (201)', 201, 'POST', '/api/lms/grades', ['action' => 'item', 'course_id' => 1, 'name' => 'Smoke item'], [], $instructorCookie);
$check('LMS-09 instructor gradebook (200)', 200, 'GET', '/api/lms/grades', [], ['course_id' => 1], $instructorCookie);
$check('LMS-10 instructor export (201)', 201, 'POST', '/api/lms/grade_export', ['course_id' => 1, 'format' => 'csv'], [], $instructorCookie);
$check('LMS-10 exports list (200)', 200, 'GET', '/api/lms/grade_export', [], [], $instructorCookie);

// ---- Admin login ----
$adminCookie = sessionCookie($app, 'admin', 'AdminPass123!');
$check('LMS-11 bulk report (201)', 201, 'POST', '/api/lms/bulk_course_report', ['report_type' => 'courses', 'semester' => 'all'], [], $adminCookie);
$check('LMS-11 reports list (200)', 200, 'GET', '/api/lms/bulk_course_report', [], [], $adminCookie);
$check('LMS-12 admin users (200)', 200, 'GET', '/api/lms/administrative_api', [], ['entity' => 'users'], $adminCookie);
$check('LMS-12 admin audit (200)', 200, 'GET', '/api/lms/administrative_api', [], ['entity' => 'audit'], $adminCookie);
$check('LMS-12 non-admin denied (403)', 403, 'GET', '/api/lms/administrative_api', [], [], $cookie);
$check('LMS-13 frontend integration (200)', 200, 'GET', '/api/lms/frontend_api_integration', [], [], $cookie);
$check('LMS-14 error response (404)', 404, 'GET', '/api/lms/error_responses', [], ['code' => 'NOT_FOUND'], $cookie);
$check('LMS-14 unknown module (404)', 404, 'GET', '/api/lms/does_not_exist', [], [], $cookie);

// ---- Pages ----
$pageChecks = [
    ['GET', '/login', 200],
    ['GET', '/dashboard', 302],
    ['GET', '/courses', 302],
    ['GET', '/api-integration', 302],
];
foreach ($pageChecks as [$method, $path, $expected]) {
    [$request] = makeRequest($method, $path);
    $response = $app->handle($request);
    $ok = $response->getStatusCode() === $expected;
    $results[] = ['label' => "page {$method} {$path}", 'ok' => $ok, 'status' => $response->getStatusCode(), 'expected' => $expected, 'uri' => $path, 'payload' => null];
}

// ---- Summary ----
$passed = count(array_filter($results, static fn ($r) => $r['ok']));
$failed = count($results) - $passed;
echo "----------------------------------------\n";
printf("Smoke checks: %d passed, %d failed\n", $passed, $failed);
if ($failed > 0) {
    foreach ($results as $r) {
        if (!$r['ok']) {
            printf("  [FAIL] %-45s got %d expected %d (%s)\n", $r['label'], $r['status'], $r['expected'], $r['uri']);
        }
    }
    exit(1);
}
echo "All smoke checks passed.\n";
exit(0);
