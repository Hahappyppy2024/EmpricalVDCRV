<?php
declare(strict_types=1);

namespace Shop\Models;

use Shop\Database;

final class PromotionRepository
{
    public static function list(): array
    {
        return Database::pdo()
            ->query('SELECT * FROM promotions ORDER BY id ASC')
            ->fetchAll();
    }

    public static function create(string $code, string $description, int $percent): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO promotions (code, description, percent_off, active) VALUES (:c, :d, :p, 1)'
        );
        $stmt->execute([':c' => strtoupper($code), ':d' => $description, ':p' => max(0, min(100, $percent))]);
        return (int)$pdo->lastInsertId();
    }

    public static function setActive(int $id, bool $active): void
    {
        Database::pdo()->prepare('UPDATE promotions SET active = :a WHERE id = :id')
            ->execute([':a' => $active ? 1 : 0, ':id' => $id]);
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM promotions WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}