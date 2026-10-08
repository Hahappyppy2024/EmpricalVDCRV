<?php

declare(strict_types=1);

namespace App\Modules;

use App\BaseService;
use App\Validator;

/**
 * SYS-11 API token manager.
 *
 * Persistent entities: User, Session, ApiTokenManager (API tokens).
 * Admins create, revoke and audit monitoring API tokens. Plain tokens are
 * only returned once at creation; the store keeps only their SHA-256 hash.
 */
final class ApiTokenManagerService extends BaseService
{
    public function list(array $filters, array $user): array
    {
        $sql = 'SELECT t.id, t.name, t.token_prefix, t.owner_id, t.created_by, t.revoked_at,
                       t.last_used_at, t.created_at, u.username AS owner_name,
                       CASE WHEN t.revoked_at IS NULL THEN \'active\' ELSE \'revoked\' END AS status
                FROM api_tokens t
                LEFT JOIN users u ON u.id = t.owner_id WHERE 1 = 1';
        $params = [];
        if (!empty($filters['status'])) {
            $sql .= ' AND ' . ($filters['status'] === 'revoked' ? 't.revoked_at IS NOT NULL' : 't.revoked_at IS NULL');
        }
        $sql .= ' ORDER BY t.id DESC';

        return $this->db->fetchAll($sql, $params);
    }

    public function item(int $id, array $user): array
    {
        return $this->db->fetchOne(
            'SELECT t.*, u.username AS owner_name,
                    CASE WHEN t.revoked_at IS NULL THEN \'active\' ELSE \'revoked\' END AS status
             FROM api_tokens t LEFT JOIN users u ON u.id = t.owner_id WHERE t.id = ?',
            [$id]
        ) ?? throw new \App\NotFoundException('Token not found.');
    }

    /**
     * Create a token. The full plain token is returned only once.
     *
     * @return array{token: array<string, mixed>, plain_token: string}
     */
    public function create(array $data, array $user): array
    {
        $this->requireRole($user, ['admin']);
        $ownerId = (int) ($data['owner_id'] ?? $user['id']);
        $owner = $this->db->fetchOne('SELECT * FROM users WHERE id = ? AND active = 1', [$ownerId]);
        if ($owner === null) {
            throw new \App\ValidationException('Owner user not found or inactive.');
        }
        (new Validator())
            ->required($data, 'name')
            ->string(['name' => $data['name'] ?? ''], 'name', 120, true)
            ->throwIfInvalid();

        $plain = 'p16_' . bin2hex(random_bytes(24));
        $id = $this->db->insert('api_tokens', [
            'name' => trim((string) $data['name']),
            'token_hash' => hash('sha256', $plain),
            'token_prefix' => 'p16_' . substr($plain, 4, 8) . '...',
            'owner_id' => $ownerId,
            'created_by' => (int) $user['id'],
            'revoked_at' => null,
            'last_used_at' => null,
            'created_at' => $this->db->now(),
        ]);
        $this->log($user, 'token_create', 'api_token_manager', 'API token "' . $data['name'] . '" created for ' . $owner['username'] . '.');

        return ['token' => $this->item($id, $user), 'plain_token' => $plain];
    }

    public function update(int $id, array $data, array $user): array
    {
        $this->requireRole($user, ['admin']);
        $row = $this->requireRow('api_tokens', $id);
        $action = (string) ($data['action'] ?? '');

        if ($action === 'revoke') {
            if ($row['revoked_at'] !== null) {
                throw new \App\ValidationException('Token is already revoked.');
            }
            $this->db->update('api_tokens', ['revoked_at' => $this->db->now()], ['id' => $id]);
            $this->log($user, 'token_revoke', 'api_token_manager', 'API token "' . $row['name'] . '" revoked.');
        } else {
            throw new \App\ValidationException('Invalid token action.');
        }

        return $this->item($id, $user);
    }

    public function delete(int $id, array $user): void
    {
        $this->requireRole($user, ['admin']);
        $row = $this->requireRow('api_tokens', $id);
        $this->db->delete('api_tokens', ['id' => $id]);
        $this->log($user, 'token_revoke', 'api_token_manager', 'API token "' . $row['name'] . '" deleted.');
    }

    public function verify(string $plainToken): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM api_tokens WHERE token_hash = ? AND revoked_at IS NULL',
            [hash('sha256', $plainToken)]
        );
        if ($row !== null) {
            $this->db->update('api_tokens', ['last_used_at' => $this->db->now()], ['id' => (int) $row['id']]);
        }

        return $row;
    }
}
