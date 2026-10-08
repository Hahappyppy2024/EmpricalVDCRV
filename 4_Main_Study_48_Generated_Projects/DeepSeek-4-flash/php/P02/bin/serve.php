<?php

declare(strict_types=1);

/**
 * Local development server.
 * Usage: php bin/serve.php [--port=8080]
 *
 * Starts the PHP built-in server with the public/ docroot. When the database
 * is missing it is initialized automatically. The Workerman WebSocket process
 * is started as a child process so live updates work out of the box.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$config = App\Config\Config::load($root);

$port = 8080;
foreach ($argv as $arg) {
    if (preg_match('/^--port=(\d+)$/', $arg, $m)) {
        $port = (int) $m[1];
    }
}
$host = '127.0.0.1';

if (!is_file($config['db_path'])) {
    echo "Database not found; initializing...\n";
    passthru(PHP_BINARY . ' ' . escapeshellarg($root . '/bin/reset_db.php'));
}

$wsLog = $root . '/storage/websocket.log';
if (function_exists('proc_open')) {
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['file', $wsLog, 'a'],
        2 => ['file', $wsLog, 'a'],
    ];
    $proc = proc_open(
        escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/bin/websocket.php'),
        $descriptors,
        $pipes,
        $root
    );
    if (is_resource($proc)) {
        fclose($pipes[0]);
        echo "WebSocket process started (log: storage/websocket.log)\n";
    }
}

$docroot = $root . '/public';
echo "Conference Review System running at http://{$host}:{$port}\n";
echo "Press Ctrl+C to stop.\n";

$command = escapeshellarg(PHP_BINARY)
    . ' -S ' . $host . ':' . $port
    . ' -t ' . escapeshellarg($docroot)
    . ' ' . escapeshellarg($docroot . '/index.php');

passthru($command);
