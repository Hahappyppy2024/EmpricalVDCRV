<?php

declare(strict_types=1);

namespace CloudFS\Services;

use CloudFS\Database\Database;

final class RealtimeService
{
    public function __construct(private Database $db)
    {
    }

    public function publish(string $eventType, array $payload): void
    {
        $this->db->insert(
            'INSERT INTO realtime_events (event_type, payload, created_at) VALUES (?, ?, datetime(\'now\'))',
            [$eventType, json_encode($payload, JSON_UNESCAPED_UNICODE)]
        );
    }
}
