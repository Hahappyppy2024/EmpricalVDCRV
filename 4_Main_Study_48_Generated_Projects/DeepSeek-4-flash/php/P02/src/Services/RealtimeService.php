<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\RealtimeEventRepository;

/**
 * Local real-time adapter. Events are persisted to SQLite and picked up by the
 * Workerman WebSocket process (bin/websocket.php), which broadcasts them to
 * connected browsers. This keeps the real-time channel fully local and
 * deterministic.
 */
final class RealtimeService
{
    public function __construct(private readonly RealtimeEventRepository $events)
    {
    }

    public function publish(string $type, array $payload): array
    {
        $id = $this->events->insert([
            'type' => $type,
            'payload' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
        return [
            'id' => $id,
            'type' => $type,
            'payload' => $payload,
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function latestId(): int
    {
        return $this->events->latestId();
    }

    public function getById(int $id): ?array
    {
        $row = $this->events->getById($id);
        if ($row === null) {
            return null;
        }
        $row['payload'] = json_decode((string) $row['payload'], true) ?: [];
        return $row;
    }

    public function since(int $id): array
    {
        $out = [];
        foreach ($this->events->afterId($id) as $row) {
            $row['payload'] = json_decode($row['payload'], true) ?: [];
            $out[] = $row;
        }
        return $out;
    }
}
