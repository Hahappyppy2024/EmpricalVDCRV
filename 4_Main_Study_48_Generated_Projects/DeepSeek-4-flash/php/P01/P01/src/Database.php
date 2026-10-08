<?php

declare(strict_types=1);

namespace App;

use PDO;
use PDOException;

/**
 * Thin PDO wrapper around the SQLite database used by every repository.
 * A single shared instance is wired into the Slim container.
 */
final class Database
{
    private PDO $pdo;

    public function __construct(private Config $config)
    {
        $path = $config->dbPath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $this->pdo = new PDO('sqlite:' . $path);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->pdo->exec('PRAGMA journal_mode = WAL');
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(static fn (string $c) => ':' . $c, $columns);
        $sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->prefix($data));
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function prefix(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            $out[':' . $key] = $value;
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(string $table, array $data, string $where, array $params = []): void
    {
        $sets = [];
        $bind = [];
        foreach ($data as $key => $value) {
            $sets[] = $key . ' = :set_' . $key;
            $bind[':set_' . $key] = $value;
        }
        foreach ($params as $key => $value) {
            $bind[':' . ltrim((string) $key, ':')] = $value;
        }
        $sql = 'UPDATE ' . $table . ' SET ' . implode(', ', $sets) . ' WHERE ' . $where;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bind);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function select(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    public function first(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function execute(string $sql, array $params = []): void
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    public function transaction(callable $fn): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $fn();
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
