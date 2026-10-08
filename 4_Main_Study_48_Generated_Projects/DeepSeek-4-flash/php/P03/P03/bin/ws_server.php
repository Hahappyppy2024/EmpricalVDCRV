<?php

declare(strict_types=1);

/**
 * Workerman WebSocket server - broadcasts order events (SHOP-08 / SHOP-13).
 *
 * The server shares the same SQLite database as the web application. It polls
 * the broadcasts table once per second and pushes new events to subscribed
 * clients, so the browser receives live order status updates.
 *
 * Run:  php bin/ws_server.php start
 */

require __DIR__ . '/../src/bootstrap.php';

use Shop\Config;
use Shop\Database;
use Shop\Repository\BroadcastRepository;
use Workerman\Connection\TcpConnection;
use Workerman\Timer;
use Workerman\Worker;

Config::load(dirname(__DIR__));

$host = (string) Config::get('WS_HOST', '0.0.0.0');
$port = Config::int('WS_PORT', 8081);

$worker = new Worker("websocket://{$host}:{$port}");
$worker->name = 'P03-order-stream';
$worker->count = 1;

$worker->onConnect = function (TcpConnection $connection): void {
    $connection->channel = 'orders';
};

$worker->onMessage = function (TcpConnection $connection, $data): void {
    $message = json_decode((string) $data, true);
    if (is_array($message) && ($message['type'] ?? '') === 'subscribe') {
        $connection->channel = (string) ($message['channel'] ?? 'orders');
        $connection->send(ws_json('subscribed', ['channel' => $connection->channel]));
    }
};

$worker->onWorkerStart = function () use ($worker): void {
    Timer::add(1, function () use ($worker): void {
        static $lastId = 0;
        try {
            $repo = new BroadcastRepository(Database::connect());
            foreach ($repo->after($lastId) as $row) {
                $lastId = (int) $row['id'];
                $channel = (string) $row['channel'];
                $payload = json_decode((string) $row['payload'], true) ?: [];
                foreach ($worker->connections as $connection) {
                    if ($connection->channel === $channel) {
                        $connection->send(ws_json((string) $row['event'], $payload));
                    }
                }
            }
        } catch (\Throwable $e) {
            // Keep the poll loop alive; connection-level errors are ignored.
        }
    });
};

Worker::runAll();
