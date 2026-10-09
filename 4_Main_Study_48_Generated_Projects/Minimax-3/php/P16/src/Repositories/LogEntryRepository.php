<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;

final class LogEntryRepository
{
    public function forFile(int $fileId, ?string $levelFilter, ?string $textFilter, int $limit = 200): array
    {
        $sql = 'SELECT * FROM log_entries WHERE log_file_id = :fid';
        $params = [':fid' => $fileId];
        if ($levelFilter !== null && $levelFilter !== '') {
            $sql .= ' AND level = :lvl';
            $params[':lvl'] = $levelFilter;
        }
        if ($textFilter !== null && $textFilter !== '') {
            $sql .= ' AND message LIKE :txt';
            $params[':txt'] = '%' . $textFilter . '%';
        }
        $sql .= ' ORDER BY ts DESC LIMIT ' . max(1, min(500, $limit));
        $stmt = Connection::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function create(int $fileId, string $ts, string $level, string $message, array $context = []): int
    {
        $stmt = Connection::get()->prepare(
            'INSERT INTO log_entries (log_file_id, ts, level, message, context)
             VALUES (:fid, :ts, :lvl, :msg, :ctx)'
        );
        $stmt->execute([
            ':fid' => $fileId,
            ':ts' => $ts,
            ':lvl' => $level,
            ':msg' => $message,
            ':ctx' => json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
        return (int)Connection::get()->lastInsertId();
    }

    public function clearForFile(int $fileId): void
    {
        $stmt = Connection::get()->prepare('DELETE FROM log_entries WHERE log_file_id = :fid');
        $stmt->execute([':fid' => $fileId]);
    }
}