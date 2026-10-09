<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Database;

final class PlanRepository
{
    public function all(): array
    {
        return Database::pdo()->query('SELECT * FROM plans ORDER BY id')->fetchAll();
    }
    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM plans WHERE id = ?');
        $stmt->execute([$id]);
        $r = $stmt->fetch();
        return $r ?: null;
    }
}