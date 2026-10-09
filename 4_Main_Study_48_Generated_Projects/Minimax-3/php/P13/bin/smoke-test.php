<?php
// Smoke test using the PHP built-in HTTP client without requiring a background server.
// Walks key URLs through the Slim app by directly invoking it with mock PSR-7 requests.

declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';

use MailServer\Application;
use Slim\Psr7\Factory\ServerRequestFactory;

function run_request(string $method, string $path, ?array $body = null, ?array $cookies = []): array
{
    $app = Application::boot();
    $request = ServerRequestFactory::createFromGlobals();
    $request = $request->withMethod($method)->withUri(new \Slim\Psr7\Uri('http', '127.0.0.1', 80, $path));
    foreach ($cookies as $k => $v) {
        $params = session_get_cookie_params();
        $_COOKIE[$k] = $v;
    }
    if ($body !== null) {
        $request = $request->withParsedBody($body);
    }
    try {
        $response = $app->handle($request);
        $response->getBody()->rewind();
        $payload = $response->getBody()->getContents();
        $code = $response->getStatusCode();
        $loc = $response->getHeaderLine('Location');
        return [$code, $payload, $loc];
    } catch (\Throwable $e) {
        return [500, 'EXC: ' . $e->getMessage(), ''];
    }
}

$results = [];

function record(array &$results, string $label, array $tuple): void
{
    [$code, $payload, $loc] = $tuple;
    $results[] = sprintf('%-50s status=%d  location=%s  body=%s', $label, $code, $loc, substr($payload, 0, 80));
}

record($results, 'GET /health', run_request('GET', '/health'));
record($results, 'GET /login', run_request('GET', '/login'));
record($results, 'GET /register', run_request('GET', '/register'));
record($results, 'GET /dashboard (no auth -> redirect)', run_request('GET', '/dashboard'));
record($results, 'GET /mail (no auth -> redirect)', run_request('GET', '/mail'));
record($results, 'GET /api/mail/account_access (no auth)', run_request('GET', '/api/mail/account_access'));

// Login as alice
$pdo = MailServer\Database\Database::connection();
$stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
$stmt->execute(['alice']);
$aliceId = (int) $stmt->fetchColumn();
$session = new MailServer\Auth\SessionService();
$session->start();
$sid = $session->login((string) $aliceId, '127.0.0.1', 'smoke');

record($results, 'GET /dashboard (alice)', run_request('GET', '/dashboard', null, ['MSAC_SID' => $sid]));
record($results, 'GET /mail (alice)', run_request('GET', '/mail', null, ['MSAC_SID' => $sid]));
record($results, 'GET /api/mail/account_access (alice)', run_request('GET', '/api/mail/account_access', null, ['MSAC_SID' => $sid]));

record($results, 'POST /api/mail/message_compose (alice)', run_request('POST', '/api/mail/message_compose', [
    'to_addresses' => 'bob@example.com',
    'subject' => 'Smoke test',
    'body_text' => 'This is a smoke test message.',
], ['MSAC_SID' => $sid]));

record($results, 'GET /api/mail/mailbox_overview (alice, no folder)', run_request('GET', '/api/mail/mailbox_overview', null, ['MSAC_SID' => $sid]));

record($results, 'POST /api/mail/contact_management (alice)', run_request('POST', '/api/mail/contact_management', [
    'name' => 'Dave Smoke',
    'email' => 'dave@example.com',
    'organization' => 'Smoke',
    'phone' => '+1-555-9999',
], ['MSAC_SID' => $sid]));

record($results, 'POST /api/mail/filters_and_rules (alice)', run_request('POST', '/api/mail/filters_and_rules', [
    'name' => 'Smoke rule',
    'conditions' => 'from_address:smoke@example.com',
    'actions' => 'move_to:Trash',
    'priority' => 50,
], ['MSAC_SID' => $sid]));

// Login as dadm
$stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
$stmt->execute(['dadm']);
$dadmId = (int) $stmt->fetchColumn();
$session = new MailServer\Auth\SessionService();
$sid = $session->login((string) $dadmId, '127.0.0.1', 'smoke');
record($results, 'GET /api/mail/domain_management/list (dadm)', run_request('GET', '/api/mail/domain_management/list', null, ['MSAC_SID' => $sid]));
record($results, 'GET /api/mail/quarantine (dadm)', run_request('GET', '/api/mail/quarantine', null, ['MSAC_SID' => $sid]));

// Login as sadm
$stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
$stmt->execute(['sadm']);
$sadmId = (int) $stmt->fetchColumn();
$session = new MailServer\Auth\SessionService();
$sid = $session->login((string) $sadmId, '127.0.0.1', 'smoke');
record($results, 'GET /api/mail/admin_audit_logs (sadm)', run_request('GET', '/api/mail/admin_audit_logs', null, ['MSAC_SID' => $sid]));
record($results, 'GET /api/mail/frontend_api_integration_and_errors (sadm)', run_request('GET', '/api/mail/frontend_api_integration_and_errors', null, ['MSAC_SID' => $sid]));

// Login as alice and try cross-role
$session = new MailServer\Auth\SessionService();
$sid = $session->login((string) $aliceId, '127.0.0.1', 'smoke');
record($results, 'GET /api/mail/admin_audit_logs (alice -> 403)', run_request('GET', '/api/mail/admin_audit_logs', null, ['MSAC_SID' => $sid]));

echo "\n=== Smoke test results ===\n";
foreach ($results as $line) {
    echo $line . "\n";
}
echo "=== Done ===\n";
