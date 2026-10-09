<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;

final class JobProfileRepository
{
    public function all(): array
    {
        return Connection::get()->query('SELECT * FROM job_profiles ORDER BY code')->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = Connection::get()->prepare('SELECT * FROM job_profiles WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByCode(string $code): ?array
    {
        $stmt = Connection::get()->prepare('SELECT * FROM job_profiles WHERE code = :c LIMIT 1');
        $stmt->execute([':c' => $code]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(string $code, string $name, string $description, string $command): int
    {
        $stmt = Connection::get()->prepare(
            'INSERT INTO job_profiles (code, name, description, command) VALUES (:c, :n, :d, :cmd)'
        );
        $stmt->execute([':c' => $code, ':n' => $name, ':d' => $description, ':cmd' => $command]);
        return (int)Connection::get()->lastInsertId();
    }
}