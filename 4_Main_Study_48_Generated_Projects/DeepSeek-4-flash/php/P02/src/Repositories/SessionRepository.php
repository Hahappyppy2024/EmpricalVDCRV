<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class SessionRepository extends BaseRepository
{
    public function table(): string
    {
        return 'sessions';
    }

    public function findByTokenHash(string $tokenHash): ?array
    {
        return $this->findOneBy('token_hash', $tokenHash);
    }

    public function findActiveByTokenHash(string $tokenHash): ?array
    {
        $st = $this->db->prepare(
            'SELECT * FROM sessions WHERE token_hash = :hash AND is_active = 1 AND expires_at > datetime(\'now\') LIMIT 1'
        );
        $st->execute(['hash' => $tokenHash]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function activeByUser(int $userId): array
    {
        return $this->findAll(['user_id' => $userId, 'is_active' => 1], 'created_at DESC');
    }

    public function deactivate(int $id): bool
    {
        return $this->update($id, ['is_active' => 0]);
    }
}
