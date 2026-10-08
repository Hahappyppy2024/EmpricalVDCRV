<?php

declare(strict_types=1);

namespace Shop\Repository;

use PDO;

abstract class Repository
{
    public function __construct(protected PDO $pdo)
    {
    }

    protected function row(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    protected function rows(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    protected function exec(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    protected function insertId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }
}
