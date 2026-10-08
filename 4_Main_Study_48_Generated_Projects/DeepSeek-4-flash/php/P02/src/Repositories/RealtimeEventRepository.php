<?php

declare(strict_types=1);

namespace App\Repositories;

final class RealtimeEventRepository extends BaseRepository
{
    public function table(): string
    {
        return 'realtime_events';
    }

    public function afterId(int $id): array
    {
        $st = $this->db->prepare(
            'SELECT * FROM realtime_events WHERE id > :id ORDER BY id ASC LIMIT 200'
        );
        $st->execute(['id' => $id]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function latestId(): int
    {
        $st = $this->db->prepare('SELECT COALESCE(MAX(id), 0) AS m FROM realtime_events');
        $st->execute();
        return (int) $st->fetchColumn();
    }
}
