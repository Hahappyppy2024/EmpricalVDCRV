<?php

declare(strict_types=1);

namespace App;

use PDO;

final class Database
{
    private ?PDO $pdo = null;

    public function __construct(private readonly string $path)
    {
    }

    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            $dir = dirname($this->path);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            $this->pdo = new PDO('sqlite:' . $this->path);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->pdo->exec('PRAGMA foreign_keys = ON');
            $this->pdo->exec('PRAGMA busy_timeout = 5000');
        }

        return $this->pdo;
    }

    public function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    /** @param array<int|string, mixed> $params */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /** @param array<int|string, mixed> $params */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $rows = $this->fetchAll($sql, $params);

        return $rows[0] ?? null;
    }

    /** @param array<int|string, mixed> $params */
    public function fetchValue(string $sql, array $params = []): mixed
    {
        $row = $this->fetchOne($sql, $params);
        if ($row === null) {
            return null;
        }

        return reset($row);
    }

    /** @param array<string, mixed> $data */
    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $sql = 'INSERT INTO ' . $table
            . ' (' . implode(', ', $columns) . ') VALUES ('
            . implode(', ', array_map(static fn (string $c) => ':' . $c, $columns)) . ')';
        $stmt = $this->pdo()->prepare($sql);
        foreach ($data as $col => $val) {
            $stmt->bindValue(':' . $col, $val);
        }
        $stmt->execute();

        return (int) $this->pdo()->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(string $table, array $data, array $where): int
    {
        $sets = [];
        $params = [];
        foreach ($data as $col => $val) {
            $sets[] = $col . ' = :set_' . $col;
            $params[':set_' . $col] = $val;
        }
        $conds = [];
        foreach ($where as $col => $val) {
            $conds[] = $col . ' = :where_' . $col;
            $params[':where_' . $col] = $val;
        }
        $sql = 'UPDATE ' . $table . ' SET ' . implode(', ', $sets) . ' WHERE ' . implode(' AND ', $conds);
        $stmt = $this->pdo()->prepare($sql);

        return $stmt->execute($params) ? $stmt->rowCount() : 0;
    }

    /** @param array<string, mixed> $where */
    public function delete(string $table, array $where): int
    {
        $conds = [];
        $params = [];
        foreach ($where as $col => $val) {
            $conds[] = $col . ' = :where_' . $col;
            $params[':where_' . $col] = $val;
        }
        $sql = 'DELETE FROM ' . $table . ' WHERE ' . implode(' AND ', $conds);
        $stmt = $this->pdo()->prepare($sql);

        return $stmt->execute($params) ? $stmt->rowCount() : 0;
    }

    public function begin(): void
    {
        $this->pdo()->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo()->commit();
    }

    public function rollback(): void
    {
        if ($this->pdo()->inTransaction()) {
            $this->pdo()->rollBack();
        }
    }

    public function execute(string $sql): void
    {
        $this->pdo()->exec($sql);
    }

    public function tableExists(string $table): bool
    {
        $row = $this->fetchOne("SELECT name FROM sqlite_master WHERE type='table' AND name = ?", [$table]);

        return $row !== null;
    }
}
