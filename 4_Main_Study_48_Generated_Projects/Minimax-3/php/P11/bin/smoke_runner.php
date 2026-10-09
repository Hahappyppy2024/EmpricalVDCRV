<?php
/**
 * Smoke test runner: receives base URL via argv and runs all checks.
 * Always fetches a fresh CSRF token before each non-GET request.
 */
$base = $argv[1] ?? null;
if (!$base) { fwrite(STDERR, "Usage: php smoke_runner.php http://host:port\n"); exit(1); }

$jar = sys_get_temp_dir() . '/aetherpanel_cookies_' . bin2hex(random_bytes(4)) . '.txt';
@unlink($jar);

function req(string $method, string $url, array $body = null): array {
    global $jar;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR      => $jar,
        CURLOPT_COOKIEFILE     => $jar,
        CURLOPT_HTTPHEADER     => ['Accept: */*'],
    ]);
    if ($method !== 'GET') curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded', 'Accept: */*']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($body));
    }
    $resp = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hdrSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return ['status' => $status, 'headers' => substr($resp, 0, $hdrSize), 'body' => substr($resp, $hdrSize)];
}
function getCsrf(string $body): ?string {
    if (preg_match('/name="_csrf"\s+value="([^"]+)"/', $body, $m)) return $m[1];
    return null;
}

/** Fetch CSRF by hitting the login page which always renders one. */
function freshCsrf(string $base): ?string {
    return getCsrf(req('GET', "$base/login")['body']);
}

/** Issue a POST that requires CSRF. */
function postCsrf(string $url, array $body, string $base): array {
    $body['_csrf'] = freshCsrf($base);
    return req('POST', $url, $body);
}

echo "=== AetherPanel P11 Smoke Test ===\n";
$total = 0; $ok = 0;
function check(string $label, int $status, array $expected): void {
    global $total, $ok;
    $total++;
    $valid = in_array($status, $expected, true);
    if ($valid) $ok++;
    echo ($valid ? '[OK]  ' : '[FAIL]') . " $label -> $status (expected: " . implode('|', $expected) . ")\n";
}

$r = req('GET', "$base/login");
check('1. GET /login', $r['status'], [200]);

$r = postCsrf("$base/login", ['login' => 'alice', 'password' => 'Password123!'], $base);
check('2. POST /login (alice)', $r['status'], [302]);

$r = req('GET', "$base/dashboard");
check('3. GET /dashboard', $r['status'], [200]);

$r = req('GET', "$base/api/host/domain_management");
$domains = json_decode($r['body'], true);
check('4. GET /api/host/domain_management', $r['status'], [200]);
echo "   first domain: " . ($domains['items'][0]['domain'] ?? 'none') . "\n";

$r = postCsrf("$base/domains", ['domain' => 'smoketest-alice.test', 'type' => 'subdomain', 'document_root' => '/home/alice/smoke'], $base);
check('5. POST /domains', $r['status'], [302, 200]);

$r = req('GET', "$base/api/host/site_management");
check('6. GET /api/host/site_management', $r['status'], [200]);

$r = req('GET', "$base/api/host/file_manager");
check('7. GET /api/host/file_manager', $r['status'], [200]);

$r = req('GET', "$base/api/host/database_management");
check('8. GET /api/host/database_management', $r['status'], [200]);

$r = req('GET', "$base/api/host/backup_and_restore");
check('9. GET /api/host/backup_and_restore', $r['status'], [200]);

$r = req('GET', "$base/api/host/ssl_certificate_management");
check('10. GET /api/host/ssl_certificate_management', $r['status'], [200]);

$r = req('GET', "$base/api/host/scheduled_tasks");
check('11. GET /api/host/scheduled_tasks', $r['status'], [200]);

$r = req('GET', "$base/api/host/resource_usage");
check('12. GET /api/host/resource_usage', $r['status'], [200]);

$r = req('GET', "$base/api/host/support_tickets");
check('13. GET /api/host/support_tickets', $r['status'], [200]);

$r = req('GET', "$base/api/host/audit_logs");
check('14. GET /api/host/audit_logs', $r['status'], [200]);

$r = req('GET', "$base/api/host/admin_operations");
check('15. GET /api/host/admin_operations (alice/customer)', $r['status'], [403]);

$r = postCsrf("$base/logout", [], $base);
check('16. POST /logout', $r['status'], [302]);

$r = postCsrf("$base/login", ['login' => 'admin', 'password' => 'Password123!'], $base);
check('17. POST /login (admin)', $r['status'], [302]);

$r = req('GET', "$base/api/host/admin_operations");
$body = json_decode($r['body'], true);
check('18. GET /api/host/admin_operations (admin)', $r['status'], [200]);
echo "   users=" . count($body['users'] ?? []) . " plans=" . count($body['plans'] ?? []) . "\n";

$r = req('GET', "$base/api/host/support_tickets");
$body = json_decode($r['body'], true);
check('19. GET /api/host/support_tickets (admin)', $r['status'], [200]);
echo "   tickets=" . count($body['items'] ?? []) . "\n";

$r = req('GET', "$base/api/host/resource_usage");
$body = json_decode($r['body'], true);
check('20. GET /api/host/resource_usage (admin sees own)', $r['status'], [200]);
echo "   avg_cpu=" . ($body['summary']['avg_cpu'] ?? 'n/a') . "\n";

postCsrf("$base/logout", [], $base);

$r = postCsrf("$base/login", ['login' => 'support', 'password' => 'Password123!'], $base);
check('21. POST /login (support)', $r['status'], [302]);

$r = req('GET', "$base/api/host/support_tickets");
$body = json_decode($r['body'], true);
check('22. GET /api/host/support_tickets (support)', $r['status'], [200]);
echo "   tickets=" . count($body['items'] ?? []) . " (all tickets)\n";

// Cross-user access: support tries to read alice's resource_usage (own only is fine)
$r = req('GET', "$base/api/host/resource_usage");
check('23. GET /api/host/resource_usage (support)', $r['status'], [200]);

// Create a ticket via API
$r = postCsrf("$base/api/host/support_tickets", [
    'subject' => 'API ticket smoke test',
    'body'    => 'Posted via API by smoke test',
    'priority'=> 'normal',
], $base);
check('24. POST /api/host/support_tickets (alice/admin/support)', $r['status'], [201]);

// Record resource usage
$r = postCsrf("$base/api/host/resource_usage", [
    'period'             => date('Y-m-d'),
    'cpu_percent'        => 5.5,
    'disk_used_mb'       => 100,
    'bandwidth_used_mb'  => 50,
    'emails_sent'        => 2,
], $base);
check('25. POST /api/host/resource_usage', $r['status'], [201]);

@unlink($jar);
echo "\n=== $ok / $total tests passed ===\n";
exit($ok === $total ? 0 : 1);