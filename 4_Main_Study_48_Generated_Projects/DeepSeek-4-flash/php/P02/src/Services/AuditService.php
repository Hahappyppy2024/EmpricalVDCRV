<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuditEventRepository;

final class AuditService
{
    public function __construct(private readonly AuditEventRepository $audit)
    {
    }

    public function record(int $userId, string $action, string $entity, int $entityId, string $detail = ''): array
    {
        $id = $this->audit->insert([
            'user_id' => $userId,
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId,
            'detail' => $detail,
        ]);
        return $this->audit->getById($id);
    }

    public function recent(int $limit = 50): array
    {
        return $this->audit->recent($limit);
    }
}
