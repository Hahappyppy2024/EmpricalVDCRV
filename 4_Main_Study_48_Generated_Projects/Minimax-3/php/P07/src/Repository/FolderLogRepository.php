<?php
declare(strict_types=1);
namespace App\Repository;

use App\Infrastructure\Database;

final class FolderLogRepository
{
    public function log(int $folderId, ?int $userId, string $action, bool $success, array $details = []): void
    {
        Database::pdo()->prepare('INSERT INTO folder_management (folder_id, user_id, action, success, details) VALUES (?, ?, ?, ?, ?)')
            ->execute([$folderId, $userId, $action, $success ? 1 : 0, json_encode($details, JSON_UNESCAPED_UNICODE)]);
    }
}