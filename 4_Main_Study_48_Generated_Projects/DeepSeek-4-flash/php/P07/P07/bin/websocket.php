<?php

declare(strict_types=1);

use CloudFS\Config;
use CloudFS\Database\Database;
use Workerman\Worker;

define('CLOUDFS_ROOT', dirname(__DIR__));
require CLOUDFS_ROOT . '/vendor/autoload.php';

$config = Config::load(CLOUDFS_ROOT);

$db = new Database($config['db_path']);

$ws = new Worker('websocket://' . $config['ws_host'] . ':' . $config['ws_port']);
$ws->count = 1;
$ws->name = 'CloudFS-realtime';

$lastId = (int) $db->value('SELECT COALESCE(MAX(id), 0) FROM realtime_events');

$ws->onWorkerStart = function () use (&$lastId, $db) {
    \Workerman\Timer::add(1, function () use (&$lastId, $db) {
        $rows = $db->all('SELECT id, event_type, payload, created_at FROM realtime_events WHERE id > ? ORDER BY id LIMIT 50', [$lastId]);
        foreach ($rows as $row) {
            $lastId = (int) $row['id'];
            $payload = json_decode((string) $row['payload'], true) ?: [];
            $message = json_encode([
                'id' => (int) $row['id'],
                'event' => $row['event_type'],
                'file' => $payload['file'] ?? null,
                'user' => $payload['user'] ?? null,
                'at' => $row['created_at'],
            ], JSON_UNESCAPED_UNICODE);
            foreach (Worker::getAllConnections() as $connection) {
                $connection->send($message);
            }
        }
    });
};

$ws->onMessage = function ($connection, $data) {
    $connection->send(json_encode(['type' => 'pong', 'received' => $data]));
};

$ws->onConnect = function ($connection) use (&$lastId) {
    $connection->send(json_encode(['type' => 'ready', 'last_event_id' => $lastId]));
};

Worker::runAll();
