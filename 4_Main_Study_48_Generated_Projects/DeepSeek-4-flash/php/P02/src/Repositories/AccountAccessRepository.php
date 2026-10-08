<?php

declare(strict_types=1);

namespace App\Repositories;

final class AccountAccessRepository extends BaseRepository
{
    public function table(): string
    {
        return 'account_access_and_recovery';
    }

    public function findPendingByToken(string $token): ?array
    {
        $st = $this->db->prepare(
            'SELECT * FROM account_access_and_recovery'
            . ' WHERE token = :token AND kind = \'reset_request\' AND status = \'pending\''
            . ' AND token_expires_at > datetime(\'now\') LIMIT 1'
        );
        $st->execute(['token' => $token]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function findByUser(int $userId): array
    {
        return $this->findAll(['user_id' => $userId], 'created_at DESC', 100);
    }
}
