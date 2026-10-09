<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;

final class ApiTokenRepository
{
    public function all(?int $ownerId = null): array
    {
        $sql = 'SELECT id, owner_id, name, token_prefix, scopes, state, last_used_at, expires_at, created_at, revoked_at
                  FROM api_tokens';
        $params = [];
        if ($ownerId !== null) {
            $sql .= ' WHERE owner_id = :oid';
            $params[':oid'] = $ownerId;
        }
        $sql .= ' ORDER BY created_at DESC';
        $stmt = Connection::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT id, owner_id, name, token_prefix, scopes, state, last_used_at, expires_at, created_at, revoked_at
               FROM api_tokens WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(int $ownerId, string $name, string $tokenHash, string $tokenPrefix, string $scopes, ?string $expiresAt): int
    {
        $stmt = Connection::get()->prepare(
            'INSERT INTO api_tokens (owner_id, name, token_hash, token_prefix, scopes, expires_at)
             VALUES (:o, :n, :h, :p, :s, :exp)'
        );
        $stmt->execute([
            ':o' => $ownerId,
            ':n' => $name,
            ':h' => $tokenHash,
            ':p' => $tokenPrefix,
            ':s' => $scopes,
            ':exp' => $expiresAt,
        ]);
        return (int)Connection::get()->lastInsertId();
    }

    public function revoke(int $id, ?int $ownerId = null): bool
    {
        $sql = 'UPDATE api_tokens SET state = \'revoked\', revoked_at = datetime(\'now\') WHERE id = :id';
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