<?php

declare(strict_types=1);

/**
 * P16 optional real-time push server (Workerman WebSocket).
 *
 * Shares the same SQLite database as the web application and broadcasts the
 * latest server-dashboard metric snapshots every 5 seconds.
 *
 * Start:   php bin/websocket.php start
 * Stop:    php bin/websocket.php stop
 * Test:    set WS_ENABLED=1 in .env, then open the dashboard.
 */

use App\Config;
use App\Database;
use Workerman\Timer;
use Workerman\Worker;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

if (file_exists($root . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable($root);
    $dotenv->safeLoad();
}

$config = new Config(array_merge(getenv() ?: [], $_ENV));
$db = new Database($config->get('db.path'));

$port = (int) $config->get('ws.port', '9090');
$worker = new Worker('websocket://0.0.0.0:' . $port);
$worker->name = 'p16-monitor-push';
$worker->count = 1;

$metrics = function () use ($db): array {
    $rows = $db->fetchAll(
        'SELECT sd.*, u.username AS recorded_by_name
         FROM server_dashboard sd
         LEFT JOIN users u ON u.id = sd.recorded_by
         WHERE sd.id IN (SELECT MAX(id) FROM server_dashboard GROUP BY server_name)
         ORDER BY sd.server_name ASC'
    );

    return ['type' => 'metrics', 'data' => $rows];
};

$worker->onWorkerStart = static function () use ($worker, $metrics): void {
    echo "P16 WebSocket push server started on port {$worker->getSocketName()}\n";
    Timer::add(5, static function () use ($worker, $metrics): void {
        $payload = json_encode($metrics(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        foreach ($worker->connections as $connection) {
            $connection->send($payload);
        }
    });
};

$worker->onConnect = static function ($connection): void {
    echo "Client connected: {$connection->getRemoteAddress()}\n";
};

$worker->onMessage = static function ($connection, $data) use ($metrics): void {
    $msg = json_decode((string) $data, true);
    $type = is_array($msg) ? (string) ($msg['type'] ?? '') : '';
    if ($type === 'hello') {
        $connection->send(json_encode(['type' => 'hello', 'status' => 'ok', 'server' => 'p16-monitor-push']));
    } elseif ($type === 'refresh') {
        $connection->send(json_encode($metrics(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
};

$worker->onClose = static function ($connection): void {
    echo "Client disconnected: {$connection->getRemoteAddress()}\n";
};

Worker::runAll();
