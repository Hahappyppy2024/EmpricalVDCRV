<?php
/**
 * bin/workerman_ws.php
 *
 * Optional Workerman-based WebSocket process. Shares the same SQLite
 * data and broadcasts a heartbeat payload to all connected clients.
 * This implementation does not depend on the Workerman composer
 * package being installed — it is a small in-process socket server
 * using stream sockets, so the project runs even without ext-event.
 *
 * Run:
 *   php bin/workerman_ws.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Workerman WS must be run from the command line.\n");
    exit(1);
}

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
if (class_exists(Dotenv\Dotenv::class) && is_file($root . '/.env')) {
    Dotenv\Dotenv::createImmutable($root)->safeLoad();
}
$config = require $root . '/config/settings.php';

$wsHost = $config['ws']['host'] ?? '127.0.0.1';
$wsPort = (int)($config['ws']['port'] ?? 8282);
$dbPath = $config['db']['path'];
if (!str_starts_with($dbPath, DIRECTORY_SEPARATOR) && !preg_match('#^[A-Za-z]:[\\\\/]#', $dbPath)) {
    $dbPath = $root . DIRECTORY_SEPARATOR . $dbPath;
}

fwrite(STDOUT, "P01 LMS WS bridge listening on {$wsHost}:{$wsPort}\n");

$ctx = stream_context_create(['socket' => ['so_reuseaddr' => true]]);
$server = @stream_socket_server("tcp://{$wsHost}:{$wsPort}", $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $ctx);
if (!$server) {
    fwrite(STDERR, "Could not bind WS server: {$errstr}\n");
    exit(1);
}
stream_set_blocking($server, false);

$clients = [];
$lastBeat = 0;

while (true) {
    $read = array_merge([$server], array_keys($clients) ? array_values(array_filter(array_map(function ($c) { return $c['sock'] ?? null; }, $clients))) : []);
    $write = null;
    $except = null;
    $changed = @stream_select($read, $write, $except, 0, 200000);
    if ($changed === false) {
        usleep(200000);
    } else {
        if (in_array($server, $read, true)) {
            $sock = @stream_socket_accept($server, 0);
            if ($sock) {
                stream_set_blocking($sock, false);
                performHandshake($sock);
                $clients[(int)$sock] = ['sock' => $sock, 'connected_at' => time()];
            }
            unset($read[array_search($server, $read, true)]);
        }
        foreach ($read as $client) {
            $key = (int)$client;
            if (!isset($clients[$key])) continue;
            $data = @fread($client, 65536);
            if ($data === false || $data === '') {
                @fclose($client);
                unset($clients[$key]);
                continue;
            }
            $decoded = decodeWsFrame($data);
            if ($decoded === null) {
                @fclose($client);
                unset($clients[$key]);
                continue;
            }
            // Echo + record any announcement-posting notification
            if (str_contains($decoded, 'ping')) {
                sendWsFrame($client, 'pong');
            }
        }
    }

    if (time() - $lastBeat >= 5) {
        $lastBeat = time();
        $payload = json_encode([
            'type' => 'heartbeat',
            'time' => date('c'),
            'course_count' => safeCount($dbPath, 'courses'),
            'user_count' => safeCount($dbPath, 'users'),
        ]);
        foreach ($clients as $c) {
            sendWsFrame($c['sock'], $payload);
        }
    }
}

function performHandshake($sock): void
{
    $req = '';
    $deadline = microtime(true) + 2.0;
    while (microtime(true) < $deadline) {
        $chunk = @fread($sock, 2048);
        if ($chunk === false) break;
        $req .= $chunk;
        if (str_contains($req, "\r\n\r\n")) break;
    }
    if (preg_match('/Sec-WebSocket-Key: (.+)\r\n/', $req, $m)) {
        $accept = base64_encode(sha1(trim($m[1]) . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
        $response = "HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: {$accept}\r\n\r\n";
        @fwrite($sock, $response);
    }
}

function decodeWsFrame(string $data): ?string
{
    if (strlen($data) < 2) return null;
    $opcode = ord($data[0]) & 0x0F;
    if ($opcode === 0x8) return null; // close
    $len = ord($data[1]) & 0x7F;
    $offset = 2;
    if ($len === 126) {
        $len = unpack('n', substr($data, 2, 2))[1];
        $offset = 4;
    } elseif ($len === 127) {
        $len = unpack('J', substr($data, 2, 8))[1];
        $offset = 10;
    }
    $payload = substr($data, $offset, $len);
    return $payload;
}

function sendWsFrame($sock, string $payload): void
{
    $frame = '';
    $frame .= chr(0x81); // FIN + text
    $len = strlen($payload);
    if ($len < 126) {
        $frame .= chr(0x80 | $len);
    } elseif ($len <= 0xFFFF) {
        $frame .= chr(0x80 | 126) . pack('n', $len);
    } else {
        $frame .= chr(0x80 | 127) . pack('J', $len);
    }
    $mask = random_bytes(4);
    $frame .= $mask;
    for ($i = 0; $i < $len; $i++) {
        $frame .= $payload[$i] ^ $mask[$i % 4];
    }
    @fwrite($sock, $frame);
}

function safeCount(string $dbPath, string $table): int
{
    try {
        if (!is_file($dbPath)) return 0;
        $pdo = new PDO('sqlite:' . $dbPath);
        return (int)$pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
}
