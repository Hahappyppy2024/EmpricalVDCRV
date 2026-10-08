<?php

declare(strict_types=1);

use App\Database\Connection;
use App\Database\Schema;
use Dotenv\Dotenv;
use Workerman\Worker;

require __DIR__ . '/../vendor/autoload.php';

$rootDir = dirname(__DIR__);
if (file_exists($rootDir . '/.env')) {
    Dotenv::createImmutable($rootDir)->safeLoad();
}

$dbPath = (string) (getenv('DB_PATH') ?: $rootDir . '/data/hosting.db');
$wsPort = (int) (getenv('WS_PORT') ?: 8081);

Connection::configure($dbPath);
Schema::ensure();

$worker = new Worker('websocket://0.0.0.0:' . $wsPort);
$worker->count = 1;
$worker->name = 'hosting-control-panel-realtime';

$worker->onWorkerStart = function ($worker) use ($wsPort) {
    $lastId = 0;
    echo "WebSocket process listening on port {$wsPort}\n";
    while (true) {
        try {
            $db = App\Database\Connection::db();
            $stmt = $db->prepare('SELECT * FROM audit_events WHERE id > ? ORDER BY id ASC');
            $stmt->execute([$lastId]);
            $rows = $stmt->fetchAll();
            foreach ($rows as $row) {
                $lastId = (int) $row['id'];
                $payload = json_encode(['type' => 'audit', 'event' => $row], JSON_UNESCAPED_SLASHES);
                foreach ($worker->connections as $conn) {
                    $conn->send($payload);
                }
            }
        } catch (Throwable $e) {
            // keep the loop alive; a later poll retries
        }
        usleep(500000);
    }
};

$worker->onMessage = function ($connection, $data) {
    $connection->send(json_encode(['type' => 'pong', 'data' => $data]));
};

Worker::runAll();
