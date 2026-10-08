<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;

/**
 * Local real-time adapter. The HTTP application publishes events into the
 * shared SQLite realtime_events queue; the Workerman WebSocket process polls
 * that queue and broadcasts each event to connections subscribed to the
 * matching channel (course room). This keeps the WebSocket process and the
 * web application on the same data store with deterministic behavior.
 */
final class RealtimeService
{
    public function __construct(private Database $db)
    {
    }

    public function publish(string $channel, array $payload): void
    {
        $this->db->insert('realtime_events', [
            'channel' => $channel,
            'payload_json' => (string) json_encode($payload, JSON_UNESCAPED_UNICODE),
            'consumed' => 0,
        ]);
    }

    public function channelForCourse(int $courseId): string
    {
        return 'course_' . $courseId;
    }

    /**
     * Fetch events for the Workerman process. Returns [channel, payload] pairs.
     *
     * @return list<array{id: int, channel: string, payload: mixed}>
     */
    public function pollUnconsumed(int $limit = 50): array
    {
        $rows = $this->db->select(
            'SELECT id, channel, payload_json FROM realtime_events WHERE consumed = 0 ORDER BY id ASC LIMIT ' . max(1, $limit)
        );
        $events = [];
        foreach ($rows as $row) {
            $events[] = [
                'id' => (int) $row['id'],
                'channel' => $row['channel'],
                'payload' => json_decode($row['payload_json'], true),
            ];
        }
        if ($events !== []) {
            $ids = array_map(static fn ($e) => (int) $e['id'], $events);
            $this->db->execute('UPDATE realtime_events SET consumed = 1 WHERE id IN (' . implode(',', $ids) . ')');
        }
        return $events;
    }
}
