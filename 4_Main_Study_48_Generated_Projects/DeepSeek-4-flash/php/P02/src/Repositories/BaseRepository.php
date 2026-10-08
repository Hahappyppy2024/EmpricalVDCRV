<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

abstract class BaseRepository
{
    protected PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    abstract public function table(): string;

    public function getById(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM ' . $this->table() . ' WHERE id = :id');
        $st->execute(['id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insert(array $data): int
    {
        $cols = array_keys($data);
        $placeholders = array_map(static fn (string $c): string => ':' . $c, $cols);
        $sql = 'INSERT INTO ' . $this->table()
            . ' (' . implode(', ', $cols) . ')'
            . ' VALUES (' . implode(', ', $placeholders) . ')';
        $st = $this->db->prepare($sql);
        $st->execute($data);
        return (int) $this->db->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        $cols = array_keys($data);
        $set = implode(', ', array_map(static fn (string $c): string => $c . ' = :' . $c, $cols));
        $data['id'] = $id;
        $st = $this->db->prepare('UPDATE ' . $this->table() . ' SET ' . $set . ' WHERE id = :id');
        return $st->execute($data);
    }

    public function delete(int $id): bool
    {
        $st = $this->db->prepare('DELETE FROM ' . $this->table() . ' WHERE id = :id');
        return $st->execute(['id' => $id]);
    }

    /**
     * @param array<string, mixed> $where
     * @return array<int, array<string, mixed>>
     */
    public function findAll(array $where = [], string $orderBy = 'id DESC', ?int $limit = null): array
    {
        $sql = 'SELECT * FROM ' . $this->table();
        $params = [];
        if ($where !== []) {
            $conds = [];
            foreach ($where as $col => $val) {
                $conds[] = $col . ' = :w_' . $col;
                $params['w_' . $col] = $val;
            }
            $sql .= ' WHERE ' . implode(' AND ', $conds);
        }
        $sql .= ' ORDER BY ' . $orderBy;
        if ($limit !== null) {
            $sql .= ' LIMIT ' . (int) $limit;
        }
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(array $where = []): int
    {
        $sql = 'SELECT COUNT(*) AS c FROM ' . $this->table();
        $params = [];
        if ($where !== []) {
            $conds = [];
            foreach ($where as $col => $val) {
                $conds[] = $col . ' = :w_' . $col;
                $params['w_' . $col] = $val;
            }
            $sql .= ' WHERE ' . implode(' AND ', $conds);
        }
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return (int) $st->fetchColumn();
    }

    public function findOneBy(string $column, mixed $value): ?array
    {
        $st = $this->db->prepare('SELECT * FROM ' . $this->table() . ' WHERE ' . $column . ' = :value LIMIT 1');
        $st->execute(['value' => $value]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }
}
