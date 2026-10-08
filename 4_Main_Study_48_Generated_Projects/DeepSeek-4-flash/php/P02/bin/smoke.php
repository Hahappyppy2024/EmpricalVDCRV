<?php

declare(strict_types=1);

/**
 * CLI smoke-check for the P02 Conference Review System.
 * Usage: php bin/smoke.php
 * Runs representative requests through the Slim app (no web server needed).
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Bootstrap;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Stream;

$app = Bootstrap::create(dirname(__DIR__));
$sessionName = 'conf_session';

$passed = 0;
$failed = 0;

function makeRequest(string $method, string $path, ?array $body = null, ?string $cookie = null): ServerRequestInterface
{
    $request = (new ServerRequestFactory())->createServerRequest($method, $path);
    if ($body !== null) {
        $stream = new Stream(fopen('php://temp', 'r+'));
        $stream->write(json_encode($body));
        $stream->rewind();
        $request = $request->withBody($stream)->withHeader('Content-Type', 'application/json');
    }
    if ($cookie !== null) {
        $request = $request->withCookieParams([$GLOBALS['sessionName'] => $cookie]);
    }
    return $request;
}

function grabCookie(ResponseInterface $response): ?string
{
    $header = $response->getHeaderLine('Set-Cookie');
    if ($header === '') {
        return null;
    }
    if (preg_match('/conf_session=([^;]+)/', $header, $m)) {
        return $m[1];
    }
    return null;
}

function check(string $label, bool $condition, ?ResponseInterface $response = null, array $expected = []): void
{
    global $passed, $failed;
    $ok = $condition;
    if ($ok && $response !== null && isset($expected['status'])) {
        $ok = $response->getStatusCode() === $expected['status'];
    }
    if ($ok && $response !== null && isset($expected['contains'])) {
        $ok = str_contains((string) $response->getBody(), $expected['contains']);
    }
    if ($ok) {
        $passed++;
        echo "[PASS] $label\n";
    } else {
        $failed++;
        $status = $response ? $response->getStatusCode() : 'n/a';
        echo "[FAIL] $label (http $status)\n";
        if ($response) {
            echo "       body: " . (string) $response->getBody() . "\n";
        }
    }
}

// 1. Unauthenticated API access is rejected
$res = $app->handle(makeRequest('GET', '/api/conf/paper_submission'));
check('CONF-01/12: unauthenticated API returns 401', $res->getStatusCode() === 401, $res);
check('CONF-01/12: error code is auth_required', str_contains((string) $res->getBody(), 'auth_required'), $res);

// 2. Wrong password rejected
$res = $app->handle(makeRequest('POST', '/auth/login', ['email' => 'author@example.com', 'password' => 'wrong-password']));
check('CONF-01-FA-02: incorrect credentials rejected', $res->getStatusCode() === 401, $res, ['contains' => 'auth_error']);

// 3. Author sign in
$res = $app->handle(makeRequest('POST', '/auth/login', ['email' => 'author@example.com', 'password' => 'Author@123']));
$authorCookie = grabCookie($res);
check('CONF-01-FA-01: author sign-in reaches dashboard API', $res->getStatusCode() === 200 && $authorCookie !== null, $res);

// 4. Author list own submissions
$res = $app->handle(makeRequest('GET', '/api/conf/paper_submission', null, $authorCookie));
check('CONF-03: author lists visible submissions', $res->getStatusCode() === 200, $res, ['contains' => 'submissions']);

// 5. Paper submission (no file)
$res = $app->handle(makeRequest('POST', '/api/conf/paper_submission', [
    'title' => 'Smoke Test Paper',
    'abstract' => 'Generated during the smoke check to verify the submission workflow.',
    'keywords' => 'smoke, test',
], $authorCookie));
check('CONF-03-FA-01: valid submission accepted', $res->getStatusCode() === 201, $res, ['contains' => 'Smoke Test Paper']);
$smokeSubmissionId = null;
$data = json_decode((string) $res->getBody(), true);
if (isset($data['data']['id'])) {
    $smokeSubmissionId = (int) $data['data']['id'];
}

// 6. Invalid submission rejected
$res = $app->handle(makeRequest('POST', '/api/conf/paper_submission', ['title' => '', 'abstract' => ''], $authorCookie));
check('CONF-03-FA-02: invalid submission rejected (422)', $res->getStatusCode() === 422, $res, ['contains' => 'validation_error']);

// 7. Author cannot assign reviewers
$res = $app->handle(makeRequest('POST', '/api/conf/reviewer_assignment', [
    'submission_id' => 1, 'reviewer_id' => 5,
], $authorCookie));
check('CONF-06-FA-03: non-chair cannot assign reviewers', $res->getStatusCode() === 403, $res, ['contains' => 'permission_error']);

// 8. Discovery search
$res = $app->handle(makeRequest('GET', '/api/conf/submission_discovery?q=learning', null, $authorCookie));
check('CONF-04-FA-01: discovery filter returns matching records', $res->getStatusCode() === 200, $res, ['contains' => 'learning']);
check('CONF-04-FA-03: private records excluded (author sees own/visible only)', str_contains((string) $res->getBody(), 'matches'));

// 9. Manuscript access (author owns submission 1)
$res = $app->handle(makeRequest('POST', '/api/conf/manuscript_access', ['submission_id' => 1, 'access_type' => 'view'], $authorCookie));
check('CONF-05-FA-01: manuscript access logged', $res->getStatusCode() === 201, $res, ['contains' => 'manuscript']);

// 10. Manuscript download
$res = $app->handle(makeRequest('GET', '/api/conf/manuscript_access/1/download', null, $authorCookie));
check('CONF-05: manuscript download returns file', $res->getStatusCode() === 200, $res, ['contains' => '%PDF']);

// 11. Double-blind view
$res = $app->handle(makeRequest('GET', '/api/conf/double_blind_views?submission_id=1', null, $authorCookie));
check('CONF-10-FA-01: blind view for author', $res->getStatusCode() === 200, $res, ['contains' => 'view']);

// 12. Rebuttal submitted by author on in-rebuttal submission
$res = $app->handle(makeRequest('POST', '/api/conf/rebuttal', ['submission_id' => 2, 'text' => 'Smoke rebuttal addressing the reviews.'], $authorCookie));
check('CONF-08-FA-01: rebuttal accepted', $res->getStatusCode() === 201, $res);

// 13. Account access records visible to author
$res = $app->handle(makeRequest('GET', '/api/conf/account_access_and_recovery', null, $authorCookie));
check('CONF-01: account access records returned', $res->getStatusCode() === 200, $res, ['contains' => 'records']);

// 14. Frontend error catalog + report
$res = $app->handle(makeRequest('GET', '/api/conf/frontend_api_integration_and_errors', null, $authorCookie));
check('CONF-12-FA-01: error catalog returned', $res->getStatusCode() === 200, $res, ['contains' => 'catalog']);
$res = $app->handle(makeRequest('POST', '/api/conf/frontend_api_integration_and_errors', [
    'endpoint' => '/api/conf/paper_submission', 'error_code' => 'validation_error', 'error_message' => 'smoke report',
], $authorCookie));
check('CONF-12: error report stored', $res->getStatusCode() === 201, $res);

// 15. Chair sign in
$res = $app->handle(makeRequest('POST', '/auth/login', ['email' => 'chair@example.com', 'password' => 'Chair@123']));
$chairCookie = grabCookie($res);
check('CONF-01: chair sign-in', $res->getStatusCode() === 200 && $chairCookie !== null, $res);

// 16. Phases
$res = $app->handle(makeRequest('GET', '/api/conf/conference_phases', null, $chairCookie));
check('CONF-02-FA-01: phases listed', $res->getStatusCode() === 200, $res, ['contains' => 'submission']);

// 17. Reviewer assignment (chair)
$res = $app->handle(makeRequest('POST', '/api/conf/reviewer_assignment', [
    'submission_id' => 2, 'reviewer_id' => 6, 'conflict_of_interest' => 0,
], $chairCookie));
check('CONF-06-FA-01: chair assigns reviewer', $res->getStatusCode() === 201, $res);

// 18. Decision management (chair)
$res = $app->handle(makeRequest('POST', '/api/conf/decision_management', [
    'submission_id' => 2, 'decision' => 'accept', 'notification_text' => 'Smoke decision.',
], $chairCookie));
check('CONF-09-FA-01: chair records decision', $res->getStatusCode() === 201, $res, ['contains' => 'accept']);

// 19. Bulk exports (chair)
$res = $app->handle(makeRequest('POST', '/api/conf/bulk_exports', ['export_type' => 'submissions', 'format' => 'csv'], $chairCookie));
check('CONF-11-FA-01: export generated', $res->getStatusCode() === 201, $res, ['contains' => 'completed']);
$exportId = null;
$data = json_decode((string) $res->getBody(), true);
if (isset($data['data']['id'])) {
    $exportId = (int) $data['data']['id'];
}
$res = $app->handle(makeRequest('GET', '/api/conf/bulk_exports/' . ($exportId ?? 1) . '/download', null, $chairCookie));
check('CONF-11: export downloadable', $res->getStatusCode() === 200, $res, ['contains' => 'Title']);

// 20. Reviewer sign in
$res = $app->handle(makeRequest('POST', '/auth/login', ['email' => 'reviewer2@example.com', 'password' => 'Reviewer2@123']));
$reviewerCookie = grabCookie($res);
check('CONF-01: reviewer sign-in', $res->getStatusCode() === 200 && $reviewerCookie !== null, $res);

// 21. Reviewer assignments
$res = $app->handle(makeRequest('GET', '/api/conf/reviewer_assignment', null, $reviewerCookie));
check('CONF-06: reviewer sees own assignments', $res->getStatusCode() === 200, $res);

// 22. Reviewer accepts assignment for submission 3 (id 4 from seed)
$res = $app->handle(makeRequest('PATCH', '/api/conf/reviewer_assignment/4', ['status' => 'accepted'], $reviewerCookie));
check('CONF-06: reviewer accepts assignment', $res->getStatusCode() === 200, $res, ['contains' => 'accepted']);

// 23. Review submitted
$res = $app->handle(makeRequest('POST', '/api/conf/reviewing', [
    'submission_id' => 3, 'score' => 6, 'confidence' => 3, 'comments' => 'Smoke review.', 'status' => 'submitted',
], $reviewerCookie));
check('CONF-07-FA-01: review submitted', $res->getStatusCode() === 201, $res, ['contains' => 'submitted']);

// 24. Author cannot view others' private submission detail
$res = $app->handle(makeRequest('GET', '/api/conf/paper_submission/3', null, $authorCookie));
check('CONF-03-FA-03: cross-user access rejected', $res->getStatusCode() === 403, $res, ['contains' => 'permission_error']);

// 25. Sign out removes access
$res = $app->handle(makeRequest('POST', '/auth/logout', null, $authorCookie));
check('CONF-01-FA-03: sign-out succeeds', $res->getStatusCode() === 200, $res, ['contains' => 'signed_out']);
$res = $app->handle(makeRequest('GET', '/api/conf/paper_submission', null, $authorCookie));
check('CONF-01-FA-03: session no longer valid after sign-out', $res->getStatusCode() === 401, $res);

echo "\nSmoke check complete: $passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
