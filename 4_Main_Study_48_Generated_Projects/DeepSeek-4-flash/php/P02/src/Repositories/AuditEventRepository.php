<?php

declare(strict_types=1);

namespace App\Repositories;

final class AuditEventRepository extends BaseRepository
{
    public function table(): string
    {
        return 'audit_events';
    }

    public function recent(int $limit = 50): array
    {
        return $this->findAll([], 'id DESC', $limit);
    }

    public function forEntity(string $entity, int $entityId): array
    {
        return $this->findAll(['entity' => $entity, 'entity_id' => $entityId], 'id DESC');
    }
}
