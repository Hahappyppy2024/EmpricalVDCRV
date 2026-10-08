<?php

declare(strict_types=1);

/**
 * Optional Workerman WebSocket notification process.
 *
 * Shares the same SQLite database as the HTTP application and broadcasts
 * `new_mail` events whenever new messages are delivered, so connected
 * browser clients can update in near real-time.
 *
 * This is optional: all workflows work with plain HTTP. No use case
 * requires WebSocket, but this process provides the real-time transport
 * adapter required by the Technology profile.
 *
 * Usage: php wss/server.php start
 */

use Workerman\Timer;
use Workerman\Worker;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/helpers.php';

$config = new P13\Config();
$port = (int) (getenv('WS_PORT') ?: 8090);

$worker = new Worker('websocket://0.0.0.0:' . $port);
$worker->count = 1;
$worker->name = 'p13-ws';

$worker->onWorkerStart = static function () use ($config): void {
    $db = new P13\Database($config);
    $lastMaxId = (int) $db->scalar('SELECT COALESCE(MAX(id), 0) FROM messages');

    Timer::add(2, static function () use ($db, &$lastMaxId): void {
        $maxId = (int) $db->scalar('SELECT COALESCE(MAX(id), 0) FROM messages');
        if ($maxId <= $lastMaxId) {
            return;
        }
        $rows = $db->select(
            'SELECT id, user_id, from_address, subject, created_at
               FROM messages
              WHERE id > ?
              ORDER BY id ASC',
            [$lastMaxId]
        );
        $lastMaxId = $maxId;
        $payload = json_encode(['type' => 'new_mail', 'events' => $rows]);
        foreach (Worker::$connections as $conn) {
            $conn->send($payload);
        }
    });
};

$worker->onConnect = static function ($connection): void {
    $connection->send(json_encode(['type' => 'hello', 'server' => 'p13-ws', 'time' => now_iso()]));
};

if (PHP_OS_FAMILY === 'Windows') {
    // Workerman on Windows runs in single-process development mode.
    Worker::$daemonize = false;
}

Worker::runAll();
