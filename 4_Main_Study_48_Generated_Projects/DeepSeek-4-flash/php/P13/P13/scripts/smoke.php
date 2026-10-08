<?php

declare(strict_types=1);

/**
 * Smoke checks against a running server.
 *
 * Usage:
 *   1. Start the app:    php scripts/seed.php && php -S 127.0.0.1:8080 -t public public/router.php
 *   2. Run the checks:   php scripts/smoke.php [base_url]
 *
 * Exercises authentication, role boundaries and one workflow per module
 * through the real HTTP endpoints.
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/helpers.php';

$base = $argv[1] ?? getenv('APP_URL') ?: 'http://127.0.0.1:8080';
$base = rtrim($base, '/');

$failures = 0;
$checks = 0;

function req(string $method, string $url, ?string $body = null, ?string $csrf = null, string $cookieHeader = '', array $headers = []): array
{
    $http = [
        'method' => $method,
        'ignore_errors' => true,
        'timeout' => 10,
        'header' => [],
    ];
    $allHeaders = array_merge([
        'Content-Type: application/json',
        'User-Agent: p13-smoke',
    ], $headers);
    if ($csrf !== null) {
        $allHeaders[] = 'X-CSRF-Token: ' . $csrf;
    }
    if ($cookieHeader !== '') {
        $allHeaders[] = 'Cookie: ' . $cookieHeader;
    }
    $http['header'] = $allHeaders;
    if ($body !== null) {
        $http['content'] = $body;
    }
    $ctx = stream_context_create(['http' => $http]);
    $raw = @file_get_contents($url, false, $ctx);
    $status = 0;
    if (isset($http_response_header[0]) && preg_match('#HTTP/\S+\s+(\d+)#', $http_response_header[0], $m)) {
        $status = (int) $m[1];
    }
    $cookies = [];
    if (is_array($http_response_header ?? null)) {
        foreach ($http_response_header as $h) {
            if (stripos($h, 'Set-Cookie:') === 0) {
                if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $h, $m)) {
                    $cookies[$m[1]] = $m[2];
                }
            }
        }
    }
    return ['status' => $status, 'body' => $raw === false ? '' : $raw, 'cookies' => $cookies];
}

function check(string $name, bool $ok, string $detail = ''): void
{
    global $failures, $checks;
    $checks++;
    if ($ok) {
        echo "  [OK]     {$name}\n";
    } else {
        $failures++;
        echo "  [FAIL]   {$name} " . ($detail !== '' ? "-> {$detail}" : '') . "\n";
    }
}

function parse_body(array $r): array
{
    $d = json_decode($r['body'], true);
    return is_array($d) ? $d : [];
}

function cookie_str(array $cookies): string
{
    $parts = [];
    foreach ($cookies as $k => $v) {
        $parts[] = "{$k}={$v}";
    }
    return implode('; ', $parts);
}

echo "Smoke checks against {$base}\n";

// 1. Health
$r = req('GET', $base . '/api/health');
check('GET /api/health', $r['status'] === 200 && (parse_body($r)['status'] ?? '') === 'ok', "status={$r['status']}");

// 2. Guest CSRF cookie from login page
$r = req('GET', $base . '/login');
$guestCsrf = $r['cookies']['p13_csrf'] ?? '';
check('GET /login issues guest CSRF cookie', $r['status'] === 200 && $guestCsrf !== '', "csrf=" . ($guestCsrf ?: 'none'));
$guestCookie = cookie_str($r['cookies']);

// 3. Unauthenticated API rejected (MAIL-02 alt flow)
$r = req('GET', $base . '/api/mail/mailbox_overview');
check('GET /api/mail/mailbox_overview without auth -> 401', $r['status'] === 401, "status={$r['status']}");

// 4. Wrong credentials rejected (MAIL-01-FA-02)
$r = req('POST', $base . '/api/auth/login', '{"login":"alice","password":"wrong-password"}', $guestCsrf, $guestCookie);
check('POST /api/auth/login with wrong password -> 401', $r['status'] === 401, "status={$r['status']}");

// 5. Correct credentials reach dashboard (MAIL-01-FA-01)
$r = req('POST', $base . '/api/auth/login', '{"login":"alice","password":"Passw0rd!"}', $guestCsrf, $guestCookie);
check('POST /api/auth/login with alice -> 200', $r['status'] === 200, "status={$r['status']}");
$aliceCookie = cookie_str($r['cookies']) . '; ' . $guestCookie;

$me = parse_body(req('GET', $base . '/api/auth/me', null, null, $aliceCookie));
check('GET /api/auth/me returns alice', ($me['user']['username'] ?? '') === 'alice', "user=" . ($me['user']['username'] ?? 'none'));

// 6. Mailbox overview (MAIL-02-FA-01)
$r = req('GET', $base . '/api/mail/mailbox_overview', null, null, $aliceCookie);
$box = parse_body($r);
check('GET /api/mail/mailbox_overview returns folders', $r['status'] === 200 && isset($box['mailbox']['folders']) && count($box['mailbox']['folders']) > 0, "status={$r['status']}");

// 7. Compose + send (MAIL-03)
$r = req('POST', $base . '/api/mail/message_compose', '{"action":"send","recipient":"bob@example.com","subject":"Smoke test","body":"Hello from the smoke check."}', 'smoke-csrf', $aliceCookie);
if ($r['status'] === 403) {
    // obtain the real CSRF token from the session cookie
    // fallback: read token from a page the session renders
    $page = req('GET', $base . '/dashboard', null, null, $aliceCookie);
    if (preg_match('/csrf:\s*"([a-f0-9]{32,64})"/', $page['body'], $m)) {
        $r = req('POST', $base . '/api/mail/message_compose', '{"action":"send","recipient":"bob@example.com","subject":"Smoke test","body":"Hello from the smoke check."}', $m[1], $aliceCookie);
    }
}
$send = parse_body($r);
check('POST /api/mail/message_compose send -> ok', $r['status'] === 200 && ($send['ok'] ?? false) === true, "status={$r['status']} body=" . substr($r['body'], 0, 120));

// 8. Drafts listed
$r = req('GET', $base . '/api/mail/message_compose', null, null, $aliceCookie);
check('GET /api/mail/message_compose lists drafts', $r['status'] === 200 && isset(parse_body($r)['compose']), "status={$r['status']}");

// 9. Message reading detail (MAIL-04)
$r = req('GET', $base . '/api/mail/message_reading/1', null, null, $aliceCookie);
check('GET /api/mail/message_reading/1 returns detail', $r['status'] === 200 && isset(parse_body($r)['message']), "status={$r['status']}");

// 10. Attachment list (MAIL-05)
$r = req('GET', $base . '/api/mail/attachment_handling', null, null, $aliceCookie);
check('GET /api/mail/attachment_handling lists attachments', $r['status'] === 200 && isset(parse_body($r)['attachments']), "status={$r['status']}");

// 11. Contact create (MAIL-06) and auditability
$r = req('POST', $base . '/api/mail/contact_management', '{"first_name":"Sam","last_name":"Smoke","email":"sam.smoke@example.com"}', 'smoke-csrf', $aliceCookie);
if ($r['status'] === 403) {
    $page = req('GET', $base . '/dashboard', null, null, $aliceCookie);
    if (preg_match('/csrf:\s*"([a-f0-9]{32,64})"/', $page['body'], $m)) {
        $r = req('POST', $base . '/api/mail/contact_management', '{"first_name":"Sam","last_name":"Smoke","email":"sam.smoke@example.com"}', $m[1], $aliceCookie);
    }
}
check('POST /api/mail/contact_management create -> ok', $r['status'] === 201 && (parse_body($r)['ok'] ?? false) === true, "status={$r['status']}");

// 12. Rules (MAIL-07)
$r = req('POST', $base . '/api/mail/filters_and_rules', '{"name":"Smoke rule","match_field":"subject","match_value":"urgent","action_type":"folder","action_value":"Work"}', 'smoke-csrf', $aliceCookie);
if ($r['status'] === 403) {
    $page = req('GET', $base . '/dashboard', null, null, $aliceCookie);
    if (preg_match('/csrf:\s*"([a-f0-9]{32,64})"/', $page['body'], $m)) {
        $r = req('POST', $base . '/api/mail/filters_and_rules', '{"name":"Smoke rule","match_field":"subject","match_value":"urgent","action_type":"folder","action_value":"Work"}', $m[1], $aliceCookie);
    }
}
check('POST /api/mail/filters_and_rules create -> ok', $r['status'] === 201 && (parse_body($r)['ok'] ?? false) === true, "status={$r['status']}");

// 13. Alice cannot access domain management (MAIL-08-FA-03 / MAIL-09-FA-03)
$r = req('GET', $base . '/api/mail/domain_management', null, null, $aliceCookie);
check('GET /api/mail/domain_management as mail_user -> 403', $r['status'] === 403, "status={$r['status']}");
$r = req('GET', $base . '/api/mail/quarantine', null, null, $aliceCookie);
check('GET /api/mail/quarantine as mail_user -> 403', $r['status'] === 403, "status={$r['status']}");
$r = req('GET', $base . '/api/mail/admin_audit_logs', null, null, $aliceCookie);
check('GET /api/mail/admin_audit_logs as mail_user -> 403', $r['status'] === 403, "status={$r['status']}");

// 14. Domain admin (dan) sees only his domain (MAIL-08)
$r = req('GET', $base . '/login');
$danGuest = cookie_str($r['cookies']);
$r = req('POST', $base . '/api/auth/login', '{"login":"dan","password":"Passw0rd!"}', $r['cookies']['p13_csrf'] ?? '', $danGuest);
$danCookie = cookie_str($r['cookies']) . '; ' . $danGuest;
$r = req('GET', $base . '/api/mail/domain_management', null, null, $danCookie);
$domains = parse_body($r);
check('GET /api/mail/domain_management as domain_admin -> ok', $r['status'] === 200 && isset($domains['domains']), "status={$r['status']}");
$names = array_column($domains['domains']['items'] ?? [], 'name');
check('Domain admin scoped to own domain only', $names === ['example.com'], 'domains=' . implode(',', $names));

// 15. Quarantine list + release (MAIL-09)
$r = req('GET', $base . '/api/mail/quarantine', null, null, $danCookie);
$q = parse_body($r);
check('GET /api/mail/quarantine as domain_admin -> ok', $r['status'] === 200 && isset($q['quarantine']['items']) && count($q['quarantine']['items']) > 0, "status={$r['status']}");
if (isset($q['quarantine']['items'][0]['id'])) {
    $qid = $q['quarantine']['items'][0]['id'];
    $page = req('GET', $base . '/quarantine', null, null, $danCookie);
    $csrf = 'smoke-csrf';
    if (preg_match('/csrf:\s*"([a-f0-9]{32,64})"/', $page['body'], $m)) {
        $csrf = $m[1];
    }
    $r = req('POST', $base . '/api/mail/quarantine', json_encode(['id' => $qid, 'action' => 'release']), $csrf, $danCookie);
    check('POST /api/mail/quarantine release -> ok', $r['status'] === 200 && (parse_body($r)['ok'] ?? false) === true, "status={$r['status']} body=" . substr($r['body'], 0, 120));
}

// 16. System admin audit logs (MAIL-10-FA-01)
$r = req('GET', $base . '/login');
$saGuest = cookie_str($r['cookies']);
$r = req('POST', $base . '/api/auth/login', '{"login":"sysadmin","password":"Passw0rd!"}', $r['cookies']['p13_csrf'] ?? '', $saGuest);
$saCookie = cookie_str($r['cookies']) . '; ' . $saGuest;
$r = req('GET', $base . '/api/mail/admin_audit_logs', null, null, $saCookie);
$audit = parse_body($r);
check('GET /api/mail/admin_audit_logs as system_admin -> ok', $r['status'] === 200 && isset($audit['events']), "status={$r['status']}");
$r = req('GET', $base . '/api/mail/admin_audit_logs?action=auth.login', null, null, $saCookie);
$filtered = parse_body($r);
$allMatch = true;
foreach ($filtered['events'] ?? [] as $ev) {
    if (($ev['action'] ?? '') !== 'auth.login') {
        $allMatch = false;
    }
}
$filteredTotal = $filtered['total'] ?? -1;
check('Audit filter action=auth.login returns only matching records', $filteredTotal > 0 && $allMatch, "total={$filteredTotal}");

// 17. Empty filter bounded response (MAIL-10-FA-02)
$r = req('GET', $base . '/api/mail/admin_audit_logs?action=nonexistent.action.xyz', null, null, $saCookie);
check('Unknown audit filter returns bounded empty response', $r['status'] === 200 && (parse_body($r)['total'] ?? -1) === 0, "status={$r['status']}");

// 18. Import/export (MAIL-11)
$r = req('POST', $base . '/api/mail/import_export', '{"kind":"export","entity_type":"contacts"}', 'smoke-csrf', $aliceCookie);
if ($r['status'] === 403) {
    $page = req('GET', $base . '/dashboard', null, null, $aliceCookie);
    if (preg_match('/csrf:\s*"([a-f0-9]{32,64})"/', $page['body'], $m)) {
        $r = req('POST', $base . '/api/mail/import_export', '{"kind":"export","entity_type":"contacts"}', $m[1], $aliceCookie);
    }
}
check('POST /api/mail/import_export export contacts -> ok', $r['status'] === 201 && (parse_body($r)['ok'] ?? false) === true, "status={$r['status']} body=" . substr($r['body'], 0, 120));

// 19. Frontend API errors (MAIL-12)
$r = req('POST', $base . '/api/mail/frontend_api_integration_and_errors', '{"scenario":"delivery_failure","response_status":502,"response_message":"Delivery temporarily failed"}', 'smoke-csrf', $aliceCookie);
if ($r['status'] === 403) {
    $page = req('GET', $base . '/dashboard', null, null, $aliceCookie);
    if (preg_match('/csrf:\s*"([a-f0-9]{32,64})"/', $page['body'], $m)) {
        $r = req('POST', $base . '/api/mail/frontend_api_integration_and_errors', '{"scenario":"delivery_failure","response_status":502,"response_message":"Delivery temporarily failed"}', $m[1], $aliceCookie);
    }
}
check('POST /api/mail/frontend_api_integration_and_errors -> ok', $r['status'] === 201, "status={$r['status']}");

// 20. Sign out removes access (MAIL-01-FA-03)
$r = req('POST', $base . '/api/auth/logout', '{}', 'smoke-csrf', $aliceCookie);
if ($r['status'] === 403) {
    $page = req('GET', $base . '/dashboard', null, null, $aliceCookie);
    if (preg_match('/csrf:\s*"([a-f0-9]{32,64})"/', $page['body'], $m)) {
        $r = req('POST', $base . '/api/auth/logout', '{}', $m[1], $aliceCookie);
    }
}
$r = req('GET', $base . '/api/mail/mailbox_overview', null, null, $aliceCookie);
check('After logout, private API access -> 401', $r['status'] === 401, "status={$r['status']}");

echo "\n{$checks} checks, {$failures} failures\n";
exit($failures > 0 ? 1 : 0);
