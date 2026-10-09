<?php
// Raw HTTP smoke test using stream sockets.
declare(strict_types=1);

function send_request(string $method, string $path, string $host = '127.0.0.1', int $port = 8092, ?string $body = null, array $cookies = []): array
{
    $sock = @stream_socket_client("tcp://$host:$port", $errno, $errstr, 5);
    if (!$sock) {
        return [0, "connect error: $errstr", []];
    }
    stream_set_timeout($sock, 5);
    $req = "$method $path HTTP/1.1\r\nHost: $host:$port\r\nConnection: close\r\nUser-Agent: smoke/1.0\r\n";
    if (!empty($cookies)) {
        $req .= "Cookie: " . http_build_query($cookies, '', '; ') . "\r\n";
    }
    if ($body !== null) {
        $req .= "Content-Type: application/x-www-form-urlencoded\r\nContent-Length: " . strlen($body) . "\r\n";
    }
    $req .= "\r\n";
    if ($body !== null) {
        $req .= $body;
    }
    fwrite($sock, $req);
    $resp = '';
    while (!feof($sock)) {
        $chunk = fread($sock, 8192);
        if ($chunk === false || $chunk === '') {
            break;
        }
        $resp .= $chunk;
    }
    fclose($sock);
    $parts = explode("\r\n\r\n", $resp, 2);
    $headers = explode("\r\n", $parts[0] ?? '');
    $bodyText = $parts[1] ?? '';
    return [$headers, $bodyText];
}

$host = '127.0.0.1';
$port = (int) ($argv[1] ?? 8092);

echo "=== Raw HTTP smoke test against $host:$port ===\n";

$tests = [
    ['GET', '/health'],
    ['GET', '/login'],
    ['GET', '/register'],
    ['GET', '/dashboard'],
    ['GET', '/mail'],
    ['GET', '/api/mail/account_access'],
];

foreach ($tests as [$m, $p]) {
    [$hdrs, $body] = send_request($m, $p, $host, $port);
    $status = explode(' ', $hdrs[0] ?? '', 3)[1] ?? '?';
    $loc = '';
    foreach ($hdrs as $h) {
        if (stripos($h, 'Location:') === 0) {
            $loc = trim(substr($h, 9));
            break;
        }
    }
    printf("%-6s %-40s status=%s loc=%s body=%s\n", $m, $p, $status, $loc, substr(preg_replace('/\s+/', ' ', $body), 0, 60));
}

// Now login as alice via raw form
[$hdrs, $body] = send_request('POST', '/login', $host, $port, http_build_query(['username' => 'alice', 'password' => 'Password123!']));
$status = explode(' ', $hdrs[0] ?? '', 3)[1] ?? '?';
$loc = '';
$cookies = [];
foreach ($hdrs as $h) {
    if (stripos($h, 'Location:') === 0) {
        $loc = trim(substr($h, 9));
    }
    if (stripos($h, 'Set-Cookie:') === 0) {
        $cookieStr = trim(substr($h, 11));
        $parts = explode(';', $cookieStr);
        $first = $parts[0] ?? '';
        if (strpos($first, '=') !== false) {
            [$k, $v] = explode('=', $first, 2);
            $cookies[$k] = $v;
        }
    }
}
printf("POST   /login (alice) -> status=%s loc=%s cookies=%s\n", $status, $loc, implode(',', array_keys($cookies)));

$auth = $cookies;
[$hdrs, $body] = send_request('GET', '/dashboard', $host, $port, null, $auth);
$status = explode(' ', $hdrs[0] ?? '', 3)[1] ?? '?';
printf("GET    /dashboard (authed) -> status=%s body=%s\n", $status, substr(preg_replace('/\s+/', ' ', $body), 0, 60));

[$hdrs, $body] = send_request('GET', '/mail', $host, $port, null, $auth);
$status = explode(' ', $hdrs[0] ?? '', 3)[1] ?? '?';
printf("GET    /mail (authed) -> status=%s body=%s\n", $status, substr(preg_replace('/\s+/', ' ', $body), 0, 60));

[$hdrs, $body] = send_request('GET', '/api/mail/account_access', $host, $port, null, $auth);
$status = explode(' ', $hdrs[0] ?? '', 3)[1] ?? '?';
printf("GET    /api/mail/account_access (authed) -> status=%s body=%s\n", $status, substr(preg_replace('/\s+/', ' ', $body), 0, 80));

[$hdrs, $body] = send_request('POST', '/api/mail/message_compose', $host, $port, http_build_query(['to_addresses' => 'bob@example.com', 'subject' => 'smoke', 'body_text' => 'hello']), $auth);
$status = explode(' ', $hdrs[0] ?? '', 3)[1] ?? '?';
printf("POST   /api/mail/message_compose -> status=%s body=%s\n", $status, substr(preg_replace('/\s+/', ' ', $body), 0, 80));

echo "=== Done ===\n";
