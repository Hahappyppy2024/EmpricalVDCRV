<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;

final class StoredFileRepository
{
    public function all(?int $ownerId = null, string $kind = ''): array
    {
        $sql = 'SELECT * FROM stored_files';
        $params = [];
        $clauses = [];
        if ($ownerId !== null) {
            $clauses[] = 'owner_id = :oid';
            $params[':oid'] = $ownerId;
        }
        if ($kind !== '') {
            $clauses[] = 'kind = :k';
            $params[':k'] = $kind;
        }
        if (!empty($clauses)) {
            $sql .= ' WHERE ' . implode(' AND ', $clauses);
        }
        $sql .= ' ORDER BY created_at DESC';
        $stmt = Connection::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findById(int $id, ?int $ownerId = null): ?array
    {
        $sql = 'SELECT * FROM stored_files WHERE id = :id';
        $params = [':id' => $id];
        if ($ownerId !== null) {
            $sql .= ' AND owner_id = :oid';
            $params[':oid'] = $ownerId;
        }
        $stmt = Connection::get()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = Connection::get()->prepare(
            'INSERT INTO stored_files (owner_id, kind, name, original_name, mime_type, size_bytes, checksum, storage_path, description)
             VALUES (:o, :k, :n, :orig, :m, :s, :c, :p, :d)'
        );
        $stmt->execute([
            ':o' => $data['owner_id'],
            ':k' => $data['kind'],
            ':n' => $data['name'],
            ':orig' => $data['original_name'],
            ':m' => $data['mime_type'],
            ':s' => $data['size_bytes'],
            ':c' => $data['checksum'],
            ':p' => $data['storage_path'],
            ':d' => $data['description'],
        ]);
        return (int)Connection::get()->lastInsertId();
    }

    public function delete(int $id, ?int $ownerId = null): bool
    {
        $sql = 'DELETE FROM stored_files WHERE id = :id';
        $params = [':id' => $id];
        if ($ownerId !== null) {
            $sql .= ' AND owner_id = :oid';
            $params[':oid'] = $ownerId;
        }
        $stmt = Connection::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }
}