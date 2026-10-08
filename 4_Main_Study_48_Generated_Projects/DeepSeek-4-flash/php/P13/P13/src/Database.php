<?php

declare(strict_types=1);

namespace P13;

use PDO;
use PDOException;

/**
 * SQLite data-access layer built on PDO.
 *
 * The application shares one connection for the HTTP process and the
 * Workerman WebSocket process (both write to the same SQLite file).
 */
final class Database
{
    private PDO $pdo;
    private string $path;

    public function __construct(private Config $config)
    {
        $this->path = (string) $config->get('database.path');
        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
                throw new PDOException("Unable to create database directory: {$dir}");
            }
        }
        $this->pdo = new PDO('sqlite:' . $this->path);
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

    public function lastInsertId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Run a SELECT and return all matching rows.
     *
     * @return array<int, array<string, mixed>>
     */
    public function select(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Run a SELECT and return the first row (or null).
     */
    public function row(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function scalar(string $sql, array $params = []): mixed
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    public function execute(string $sql, array $params = []): void
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }
}
