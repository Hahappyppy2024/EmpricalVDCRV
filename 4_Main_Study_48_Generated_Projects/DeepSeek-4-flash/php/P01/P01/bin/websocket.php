<?php

declare(strict_types=1);

/**
 * Local real-time WebSocket process (Workerman). Shares the same SQLite
 * database as the web application: it polls the realtime_events queue and
 * broadcasts each event to every connection subscribed to the matching
 * course channel.
 *
 * Usage:
 *   php bin/websocket.php start        (foreground)
 *   php bin/websocket.php start -d     (daemon)
 *   php bin/websocket.php stop
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Workerman\Connection\TcpConnection;
use Workerman\Worker;

$root = dirname(__DIR__);
$config = new App\Config($root);
$db = new App\Database($config);
$realtime = new App\Services\RealtimeService($db);

$worker = new Worker('websocket://' . $config->wsHost() . ':' . $config->wsPort());
$worker->name = 'P01-LMS-WebSocket';
$worker->count = 1;

/** @var array<int, array{connection: TcpConnection, channels: array<string, bool>}> $rooms */
$rooms = [];

$worker->onConnect = static function (TcpConnection $connection): void {
    global $rooms;
    $rooms[$connection->id] = ['connection' => $connection, 'channels' => []];
};

$worker->onMessage = static function (TcpConnection $connection, $data): void {
    global $rooms;
    $payload = json_decode((string) $data, true);
    if (!is_array($payload)) {
        return;
    }
    if (($payload['type'] ?? '') === 'join' && isset($payload['channel'])) {
        $channel = (string) preg_replace('/[^a-zA-Z0-9_]/', '', (string) $payload['channel']);
        $rooms[$connection->id]['channels'][$channel] = true;
        $connection->send((string) json_encode(['type' => 'joined', 'channel' => $channel]));
    }
};

$worker->onClose = static function (TcpConnection $connection): void {
    global $rooms;
    unset($rooms[$connection->id]);
};

// Poll the shared SQLite queue every second and broadcast unconsumed events
// to every connection subscribed to the matching channel. The timer is
// registered once the event loop is running (after Worker::runAll()).
$worker->onWorkerStart = static function () use ($db, $realtime, &$rooms): void {
    \Workerman\Timer::add(1, static function () use ($db, $realtime, &$rooms): void {
        $events = $realtime->pollUnconsumed(50);
        foreach ($events as $event) {
            $channel = (string) $event['channel'];
            $frame = (string) json_encode($event['payload'] ?? []);
            foreach ($rooms as $room) {
                if (isset($room['channels'][$channel])) {
                    try {
                        $room['connection']->send($frame);
                    } catch (\Throwable $e) {
                        // Connection closed between ticks; it is cleaned up onClose.
                    }
                }
            }
        }
    });
};

Worker::runAll();
