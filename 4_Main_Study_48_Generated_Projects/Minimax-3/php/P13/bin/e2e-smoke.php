<?php
// End-to-end smoke test for every use case via raw HTTP.
declare(strict_types=1);

function send(string $method, string $path, string $host, int $port, ?string $body = null, array $cookies = [], ?string $multipart = null): array
{
    $sock = @stream_socket_client("tcp://$host:$port", $errno, $errstr, 5);
    if (!$sock) {
        return [[], "connect error: $errstr"];
    }
    stream_set_timeout($sock, 5);
    $req = "$method $path HTTP/1.1\r\nHost: $host:$port\r\nConnection: close\r\nUser-Agent: e2e/1.0\r\n";
    if (!empty($cookies)) {
        $req .= "Cookie: " . http_build_query($cookies, '', '; ') . "\r\n";
    }
    if ($multipart !== null) {
        $boundary = 'E2EBoundary' . bin2hex(random_bytes(4));
        $wrapped = "--$boundary\r\n" . $multipart . "\r\n--$boundary--\r\n";
        $req .= "Content-Type: multipart/form-data; boundary=$boundary\r\nContent-Length: " . strlen($wrapped) . "\r\n";
        $req .= "\r\n" . $wrapped;
    } elseif ($body !== null) {
        $req .= "Content-Type: application/x-www-form-urlencoded\r\nContent-Length: " . strlen($body) . "\r\n";
        $req .= "\r\n" . $body;
    } else {
        $req .= "\r\n";
    }
    fwrite($sock, $req);
    $resp = '';
    while (!feof($sock)) {
        $chunk = fread($sock, 8192);
        if ($chunk === false || $chunk === '') break;
        $resp .= $chunk;
    }
    fclose($sock);
    $parts = explode("\r\n\r\n", $resp, 2);
    $headers = explode("\r\n", $parts[0] ?? '');
    $bodyText = $parts[1] ?? '';
    return [$headers, $bodyText];
}

function parse_cookies(array $headers): array
{
    $cookies = [];
    foreach ($headers as $h) {
        if (stripos($h, 'Set-Cookie:') === 0) {
            $cookieStr = trim(substr($h, 11));
            $first = explode(';', $cookieStr, 2)[0];
            if (strpos($first, '=') !== false) {
                [$k, $v] = explode('=', $first, 2);
                $cookies[$k] = $v;
            }
        }
    }
    return $cookies;
}

function status(array $headers): string
{
    return explode(' ', $headers[0] ?? '', 3)[1] ?? '?';
}

function location(array $headers): string
{
    foreach ($headers as $h) {
        if (stripos($h, 'Location:') === 0) return trim(substr($h, 9));
    }
    return '';
}

function extract_json(string $body, string $key): string
{
    $j = json_decode($body, true);
    if (!is_array($j)) return '';
    $parts = explode('.', $key);
    $cur = $j;
    foreach ($parts as $p) {
        if (is_array($cur) && array_key_exists($p, $cur)) {
            $cur = $cur[$p];
        } else {
            return '';
        }
    }
    return is_scalar($cur) ? (string) $cur : json_encode($cur);
}

$host = '127.0.0.1';
$port = (int) ($argv[1] ?? 9878);

echo "=== End-to-end smoke test against $host:$port ===\n\n";

$totalTests = 0;
$passed = 0;

function expect(string $label, string $actual, string $expected, string $body = ''): void
{
    global $totalTests, $passed;
    $totalTests++;
    $ok = ($actual === $expected);
    if ($ok) $passed++;
    printf("%s %-55s expected=%s actual=%s\n", $ok ? 'PASS' : 'FAIL', $label, $expected, $actual);
    if (!$ok && $body !== '') {
        echo "    body: " . substr(preg_replace('/\s+/', ' ', $body), 0, 120) . "\n";
    }
}

// --- MAIL-01: Account access (unauthenticated) ---
[$h, $b] = send('GET', '/health', $host, $port);
expect('MAIL-01 health', status($h), '200', $b);

[$h, $b] = send('GET', '/login', $host, $port);
expect('MAIL-01 login page renders', status($h), '200', $b);

[$h, $b] = send('GET', '/dashboard', $host, $port);
expect('MAIL-01 dashboard unauth redirects', status($h), '302', $b);
expect('MAIL-01 dashboard unauth location', location($h), '/login');

[$h, $b] = send('GET', '/api/mail/account_access', $host, $port);
expect('MAIL-01 API unauth -> 401', status($h), '401', $b);

[$h, $b] = send('POST', '/login', $host, $port, http_build_query(['username' => 'alice', 'password' => 'Password123!']));
expect('MAIL-01 login good -> 302 /dashboard', status($h) . '/' . location($h), '302//dashboard', $b);
$cookies = parse_cookies($h);
expect('MAIL-01 MSAC_SID cookie issued', isset($cookies['MSAC_SID']) ? 'yes' : 'no', 'yes');

[$h, $b] = send('POST', '/login', $host, $port, http_build_query(['username' => 'alice', 'password' => 'WRONG']));
expect('MAIL-01 login bad -> 401', status($h), '401', $b);

[$h, $b] = send('POST', '/logout', $host, $port, '', $cookies);
expect('MAIL-01 logout -> 302', status($h), '302', $b);
$cookies = [];
[$h, $b] = send('GET', '/dashboard', $host, $port, null, $cookies);
expect('MAIL-01 after logout dashboard redirects', status($h), '302', $b);

// Re-login for the rest of the suite
[$h, $b] = send('POST', '/login', $host, $port, http_build_query(['username' => 'alice', 'password' => 'Password123!']));
$cookies = parse_cookies($h);

// --- MAIL-02: Mailbox overview ---
[$h, $b] = send('GET', '/mail', $host, $port, null, $cookies);
expect('MAIL-02 mailbox page renders', status($h), '200', $b);

[$h, $b] = send('GET', '/api/mail/mailbox_overview', $host, $port, null, $cookies);
expect('MAIL-02 mailbox overview api', status($h), '200', $b);

[$h, $b] = send('POST', '/api/mail/mailbox_overview', $host, $port, http_build_query(['name' => 'Newsletters', 'folder_type' => 'custom']), $cookies);
expect('MAIL-02 create folder', status($h), '200', $b);

// --- MAIL-03: Message compose ---
[$h, $b] = send('GET', '/mail/compose', $host, $port, null, $cookies);
expect('MAIL-03 compose page renders', status($h), '200', $b);

[$h, $b] = send('POST', '/api/mail/message_compose', $host, $port, http_build_query([
    'to_addresses' => 'bob@example.com',
    'subject' => 'E2E hello',
    'body_text' => 'Hi from E2E test',
]), $cookies);
expect('MAIL-03 send message', status($h), '201', $b);
$msgId = extract_json($b, 'id');

[$h, $b] = send('POST', '/api/mail/message_compose', $host, $port, http_build_query([
    'to_addresses' => 'not-an-email',
    'subject' => 'bad',
    'body_text' => 'bad',
]), $cookies);
expect('MAIL-03 invalid recipient -> 422', status($h), '422', $b);

// --- MAIL-04: Message reading ---
[$h, $b] = send('GET', "/mail/message/$msgId", $host, $port, null, $cookies);
expect('MAIL-04 message page renders', status($h), '200', $b);

[$h, $b] = send('GET', "/api/mail/message_reading?id=$msgId", $host, $port, null, $cookies);
expect('MAIL-04 read message api', status($h), '200', $b);

[$h, $b] = send('GET', "/api/mail/message_reading?id=99999", $host, $port, null, $cookies);
expect('MAIL-04 missing -> 404', status($h), '404', $b);

[$h, $b] = send('PATCH', "/api/mail/message_reading/$msgId", $host, $port, http_build_query(['action' => 'star']), $cookies);
expect('MAIL-04 star action', status($h), '200', $b);

// --- MAIL-05: Attachment handling ---
[$tmpFile = tempnam(sys_get_temp_dir(), 'att')];
file_put_contents($tmpFile, "Hello attachment world\n");
$mp  = "Content-Disposition: form-data; name=\"file\"; filename=\"hello.txt\"\r\n";
$mp .= "Content-Type: text/plain\r\n\r\n";
$mp .= file_get_contents($tmpFile);
[$h, $b] = send('POST', '/api/mail/attachment_handling', $host, $port, null, $cookies, $mp);
expect('MAIL-05 upload', status($h), '201', $b);
$attId = extract_json($b, 'id');

[$h, $b] = send('GET', "/mail/attachments/$attId/download", $host, $port, null, $cookies);
expect('MAIL-05 download', status($h), '200', $b);

$mp2  = "Content-Disposition: form-data; name=\"file\"; filename=\"big.bin\"\r\n";
$mp2 .= "Content-Type: application/octet-stream\r\n\r\n";
$mp2 .= str_repeat('A', 1024);
[$h, $b] = send('POST', '/api/mail/attachment_handling', $host, $port, null, $cookies, $mp2);
expect('MAIL-05 upload .bin ok', status($h), '201', $b);

// --- MAIL-06: Contact management ---
[$h, $b] = send('POST', '/api/mail/contact_management', $host, $port, http_build_query([
    'name' => 'Eve E2E',
    'email' => 'eve@example.com',
    'organization' => 'Test',
    'phone' => '+1-555-1212',
]), $cookies);
expect('MAIL-06 add contact', status($h), '201', $b);
$contactId = extract_json($b, 'id');

[$h, $b] = send('PATCH', "/api/mail/contact_management/$contactId", $host, $port, http_build_query([
    'name' => 'Eve Updated',
    'email' => 'eve@example.com',
]), $cookies);
expect('MAIL-06 edit contact', status($h), '200', $b);

[$h, $b] = send('GET', '/api/mail/contact_management', $host, $port, null, $cookies);
expect('MAIL-06 list contacts', status($h), '200', $b);

// --- MAIL-07: Filters and rules ---
[$h, $b] = send('POST', '/api/mail/filters_and_rules', $host, $port, http_build_query([
    'name' => 'E2E rule',
    'conditions' => 'from_address:eve@example.com',
    'actions' => 'move_to:Archive',
    'priority' => 50,
]), $cookies);
expect('MAIL-07 add rule', status($h), '201', $b);

// --- MAIL-11: Import/export (also as mail_user) ---
$csv = "name,email,organization,phone,notes\nFrank Imported,frank@example.com,Acme,,Imported via CSV\n";
$imp = "Content-Disposition: form-data; name=\"file\"; filename=\"contacts.csv\"\r\nContent-Type: text/csv\r\n\r\n$csv";
[$h, $b] = send('POST', '/mail/import-export/import', $host, $port, null, $cookies, $imp);
expect('MAIL-11 import contacts (302)', status($h), '302', $b);

[$h, $b] = send('GET', '/mail/import-export/export', $host, $port, null, $cookies);
expect('MAIL-11 export contacts', status($h), '200', $b);

// --- MAIL-12: Frontend API errors ---
[$h, $b] = send('POST', '/api/mail/frontend_api_integration_and_errors', $host, $port, http_build_query([
    'endpoint' => '/api/mail/message_compose',
    'error_code' => '422',
    'error_state' => 'invalid_recipient',
    'message' => 'Recipient address missing domain',
]), $cookies);
expect('MAIL-12 log api error', status($h), '201', $b);

// --- Switch to dadm for MAIL-08 / MAIL-09 ---
[$h, $b] = send('POST', '/login', $host, $port, http_build_query(['username' => 'dadm', 'password' => 'Password123!']));
$dadmCookies = parse_cookies($h);

[$h, $b] = send('GET', '/api/mail/domain_management/list', $host, $port, null, $dadmCookies);
expect('MAIL-08 list domains as dadm', status($h), '200', $b);

[$h, $b] = send('GET', '/mail/domains', $host, $port, null, $dadmCookies);
expect('MAIL-08 domains page renders', status($h), '200', $b);

[$h, $b] = send('GET', '/api/mail/quarantine', $host, $port, null, $dadmCookies);
expect('MAIL-09 list quarantine as dadm', status($h), '200', $b);

[$h, $b] = send('PATCH', '/api/mail/quarantine/1', $host, $port, http_build_query(['decision' => 'release']), $dadmCookies);
expect('MAIL-09 release quarantine item', status($h), '200', $b);

// mail_user should not be able to do these
[$h, $b] = send('GET', '/api/mail/quarantine', $host, $port, null, $cookies);
expect('MAIL-09 mail_user quarantine -> 403', status($h), '403', $b);

// --- Switch to sadm for MAIL-10 ---
[$h, $b] = send('POST', '/login', $host, $port, http_build_query(['username' => 'sadm', 'password' => 'Password123!']));
$sadmCookies = parse_cookies($h);

[$h, $b] = send('GET', '/api/mail/admin_audit_logs', $host, $port, null, $sadmCookies);
expect('MAIL-10 list audit as sadm', status($h), '200', $b);

[$h, $b] = send('GET', '/mail/audit', $host, $port, null, $sadmCookies);
expect('MAIL-10 audit page renders', status($h), '200', $b);

// mail_user should be blocked
[$h, $b] = send('GET', '/api/mail/admin_audit_logs', $host, $port, null, $cookies);
expect('MAIL-10 mail_user audit -> 403', status($h), '403', $b);

echo "\n=== Summary: $passed / $totalTests passed ===\n";
if ($passed === $totalTests) {
    echo "All tests passed!\n";
    exit(0);
} else {
    echo "Some tests failed.\n";
    exit(1);
}
