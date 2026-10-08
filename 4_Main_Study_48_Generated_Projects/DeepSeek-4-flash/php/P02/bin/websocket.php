<?php

declare(strict_types=1);

use App\Repositories\RealtimeEventRepository;
use Workerman\Worker;

/**
 * Local Workerman WebSocket process sharing the same SQLite database.
 * Usage: php bin/websocket.php
 *
 * Broadcasts persisted realtime_events to connected browsers. Requires
 * ext-pcntl or ext-event for the broadcast timer. On Windows without those
 * extensions the process still serves the WebSocket handshake, and the
 * browser falls back to the HTTP polling endpoint
 * (GET /api/conf/realtime_events?since=N). In Docker/Linux the timer is
 * available and live broadcasting works out of the box.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$config = App\Config\Config::load($root);

$pdo = App\Repositories\Database::connect($config['db_path']);
$events = new RealtimeEventRepository($pdo);

$timerAvailable = function_exists('pcntl_alarm') || class_exists('Event');

$worker = new Worker('websocket://0.0.0.0:' . $config['ws_port']);
$worker->name = 'conference-review-ws';
$worker->count = 1;

$lastBroadcast = $events->latestId();

$worker->onConnect = static function ($connection): void {
    $connection->send(json_encode([
        'type' => 'connected',
        'data' => ['message' => 'Live event feed connected.'],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
};

$worker->onMessage = static function ($connection, $data): void {
    $connection->send(json_encode(['type' => 'pong', 'data' => []]));
};

$worker->onWorkerStart = static function () use ($timerAvailable, $events, &$lastBroadcast, $worker): void {
    if (!$timerAvailable) {
        echo "Broadcast timer unavailable (no pcntl/event); HTTP polling fallback active.\n";
        return;
    }
    Workerman\Timer::add(1, static function () use ($events, &$lastBroadcast, $worker): void {
        $rows = $events->afterId($lastBroadcast);
        foreach ($rows as $row) {
            $lastBroadcast = (int) $row['id'];
            $message = json_encode([
                'id' => (int) $row['id'],
                'type' => $row['type'],
                'data' => json_decode((string) $row['payload'], true),
                'created_at' => $row['created_at'],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            foreach ($worker->connections as $connection) {
                $connection->send($message);
            }
        }
    });
};

echo "WebSocket server listening on ws://127.0.0.1:" . $config['ws_port'] . " (Workerman)\n";

Worker::runAll();
