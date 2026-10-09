<?php
declare(strict_types=1);
namespace App\Repository;

use App\Infrastructure\Database;

final class VersionLogRepository
{
    public function log(int $versionId, ?int $userId, string $action, bool $success, array $details = []): void
    {
        Database::pdo()->prepare('INSERT INTO version_history (version_id, user_id, action, success, details) VALUES (?, ?, ?, ?, ?)')
            ->execute([$versionId, $userId, $action, $success ? 1 : 0, json_encode($details, JSON_UNESCAPED_UNICODE)]);
    }
}