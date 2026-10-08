<?php

declare(strict_types=1);

namespace App\Services\Workflows;

use App\Services\RealtimeService;
use App\Services\WorkflowException;

/**
 * Real-time events exposed over ordinary HTTP. The browser client polls this
 * endpoint; the optional Workerman WebSocket process (bin/websocket.php)
 * broadcasts the same events for environments where pcntl/event is available.
 */
final class RealtimeEventsService implements WorkflowInterface
{
    public function __construct(private readonly RealtimeService $realtime)
    {
    }

    public function listFor(array $user, array $query): array
    {
        $since = max(0, (int) ($query['since'] ?? 0));
        return [
            'events' => $this->realtime->since($since),
            'latest_id' => $this->realtime->latestId(),
        ];
    }

    public function show(array $user, int $id): array
    {
        $event = $this->realtime->getById($id);
        if ($event === null) {
            throw new WorkflowException('not_found', 404, [], 'Event not found.');
        }
        return $event;
    }

    public function create(array $user, array $input): array
    {
        throw new WorkflowException('permission_error', 403, [], 'Events are published by the system only.');
    }

    public function update(array $user, int $id, array $input): array
    {
        throw new WorkflowException('permission_error', 403, [], 'Events are published by the system only.');
    }
}
