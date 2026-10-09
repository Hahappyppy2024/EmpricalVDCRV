<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;

final class LogFileRepository
{
    public function all(): array
    {
        return Connection::get()->query('SELECT * FROM log_files ORDER BY name')->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = Connection::get()->prepare('SELECT * FROM log_files WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByName(string $name): ?array
    {
        $stmt = Connection::get()->prepare('SELECT * FROM log_files WHERE name = :n LIMIT 1');
        $stmt->execute([':n' => $name]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(string $name, string $path, int $size, string $source, string $description): int
    {
        $stmt = Connection::get()->prepare(
            'INSERT INTO log_files (name, path, size_bytes, source, description)
             VALUES (:n, :p, :s, :src, :d)'
        );
        $stmt->execute([':n' => $name, ':p' => $path, ':s' => $size, ':src' => $source, ':d' => $description]);
        return (int)Connection::get()->lastInsertId();
    }

    public function touch(int $id, int $size): void
    {
        $stmt = Connection::get()->prepare(
            'UPDATE log_files SET last_seen_at = datetime(\'now\'), size_bytes = :s WHERE id = :id'
        );
        $stmt->execute([':s' => $size, ':id' => $id]);
    }
}