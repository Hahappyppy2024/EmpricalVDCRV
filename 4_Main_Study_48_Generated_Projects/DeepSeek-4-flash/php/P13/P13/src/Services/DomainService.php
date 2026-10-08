<?php

declare(strict_types=1);

namespace P13\Services;

use P13\Audit;
use P13\Database;
use P13\Validation;

/**
 * Domain management service (MAIL-08): domains, aliases, quotas and mailbox
 * status, scoped by the admin's role and ownership. Privileged operations
 * are audited.
 */
final class DomainService
{
    public function __construct(private Database $db, private Audit $audit)
    {
    }

    /**
     * List domains visible to the actor. Domain admins only see their own
     * domain; system admins see every domain (MAIL-08 ownership boundary).
     */
    public function list(array $actor, string $q = ''): array
    {
        if ($actor['role'] === 'system_admin') {
            $where = ['1 = 1'];
            $params = [];
        } else {
            $where = ['id = ?'];
            $params = [$actor['domain_id'] ?? null];
        }
        if ($q !== '') {
            $where[] = 'name LIKE ?';
            $params[] = '%' . $q . '%';
        }
        $clause = implode(' AND ', $where);
        $items = $this->db->select(
            'SELECT d.*,
                    (SELECT COUNT(*) FROM users u WHERE u.domain_id = d.id) AS mailbox_count
               FROM domains d
              WHERE ' . $clause . '
              ORDER BY d.id ASC',
            $params
        );
        foreach ($items as &$item) {
            $item['aliases'] = json_decode((string) $item['aliases'], true) ?: [];
        }
        unset($item);
        return ['items' => $items, 'total' => count($items)];
    }

    /**
     * @return array{ok: bool, id?: int, domain?: array<string, mixed>, errors?: array<string, string>}
     */
    public function create(array $actor, string $name, int $quotaMb, int $mailboxLimit, array $aliases): array
    {
        $name = trim(strtolower($name));
        if ($name === '' || !preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $name)) {
            return ['ok' => false, 'errors' => ['name' => 'A valid domain name is required.']];
        }
        if (!Validation::intRange($quotaMb, 1, 1048576)) {
            return ['ok' => false, 'errors' => ['quota_mb' => 'Quota must be between 1 and 1048576 MB.']];
        }
        if (!Validation::intRange($mailboxLimit, 1, 100000)) {
            return ['ok' => false, 'errors' => ['mailbox_limit' => 'Mailbox limit must be between 1 and 100000.']];
        }
        $exists = $this->db->row('SELECT id FROM domains WHERE name = ?', [$name]);
        if ($exists !== null) {
            return ['ok' => false, 'errors' => ['name' => 'Domain already exists.']];
        }
        $this->db->execute(
            'INSERT INTO domains (name, status, quota_mb, mailbox_limit, aliases, created_at)
             VALUES (?, \'active\', ?, ?, ?, datetime(\'now\'))',
            [$name, $quotaMb, $mailboxLimit, json_encode(array_values($aliases))]
        );
        $id = $this->db->lastInsertId();
        $domain = $this->db->row('SELECT * FROM domains WHERE id = ?', [$id]);
        $this->audit->log((int) $actor['id'], (string) $actor['username'], (string) $actor['role'], 'domain.create', 'Domain', (string) $id, [
            'name' => $name,
            'quota_mb' => $quotaMb,
        ]);
        return ['ok' => true, 'id' => $id, 'domain' => $domain];
    }

    /**
     * @return array{ok: bool, domain?: array<string, mixed>, errors?: array<string, string>}
     */
    public function update(array $actor, int $domainId, array $data): array
    {
        $domain = $this->scopedDomain($actor, $domainId);
        if ($domain === null) {
            return ['ok' => false, 'errors' => ['domain' => 'Unknown or out-of-scope domain.']];
        }
        $status = $data['status'] ?? $domain['status'];
        if (!Validation::in($status, ['active', 'suspended'])) {
            return ['ok' => false, 'errors' => ['status' => 'Invalid domain status.']];
        }
        $quotaMb = (int) ($data['quota_mb'] ?? $domain['quota_mb']);
        $mailboxLimit = (int) ($data['mailbox_limit'] ?? $domain['mailbox_limit']);
        if (!Validation::intRange($quotaMb, 1, 1048576)) {
            return ['ok' => false, 'errors' => ['quota_mb' => 'Invalid quota.']];
        }
        if (!Validation::intRange($mailboxLimit, 1, 100000)) {
            return ['ok' => false, 'errors' => ['mailbox_limit' => 'Invalid mailbox limit.']];
        }
        $aliases = isset($data['aliases']) && is_array($data['aliases'])
            ? array_values(array_filter(array_map('trim', $data['aliases']), fn ($a) => $a !== ''))
            : (json_decode((string) $domain['aliases'], true) ?: []);
        $this->db->execute(
            'UPDATE domains SET status = ?, quota_mb = ?, mailbox_limit = ?, aliases = ? WHERE id = ?',
            [$status, $quotaMb, $mailboxLimit, json_encode($aliases), $domainId]
        );

        if (isset($data['mailbox_user_id'], $data['mailbox_status'])) {
            $result = $this->setMailboxStatus($actor, $domainId, (int) $data['mailbox_user_id'], (string) $data['mailbox_status']);
            if (!$result['ok']) {
                return $result;
            }
        }

        $updated = $this->db->row('SELECT * FROM domains WHERE id = ?', [$domainId]);
        $updated['aliases'] = json_decode((string) $updated['aliases'], true) ?: [];
        $this->audit->log((int) $actor['id'], (string) $actor['username'], (string) $actor['role'], 'domain.update', 'Domain', (string) $domainId, [
            'status' => $status,
            'quota_mb' => $quotaMb,
            'mailbox_limit' => $mailboxLimit,
        ]);
        return ['ok' => true, 'domain' => $updated];
    }

    /**
     * Enable/disable a mailbox belonging to the domain.
     *
     * @return array{ok: bool, errors?: array<string, string>, message?: string}
     */
    public function setMailboxStatus(array $actor, int $domainId, int $targetUserId, string $status): array
    {
        $domain = $this->scopedDomain($actor, $domainId);
        if ($domain === null) {
            return ['ok' => false, 'errors' => ['domain' => 'Unknown or out-of-scope domain.']];
        }
        if (!Validation::in($status, ['active', 'disabled'])) {
            return ['ok' => false, 'errors' => ['mailbox_status' => 'Invalid mailbox status.']];
        }
        $user = $this->db->row('SELECT * FROM users WHERE id = ? AND domain_id = ?', [$targetUserId, $domainId]);
        if ($user === null) {
            return ['ok' => false, 'errors' => ['mailbox_user_id' => 'Mailbox does not belong to this domain.']];
        }
        $this->db->execute('UPDATE users SET status = ? WHERE id = ?', [$status, $targetUserId]);
        $this->audit->log((int) $actor['id'], (string) $actor['username'], (string) $actor['role'], 'domain.mailbox_status', 'User', (string) $targetUserId, [
            'status' => $status,
            'domain_id' => $domainId,
        ]);
        return ['ok' => true, 'message' => 'Mailbox status updated.'];
    }

    private function scopedDomain(array $actor, int $domainId): ?array
    {
        if ($actor['role'] === 'system_admin') {
            return $this->db->row('SELECT * FROM domains WHERE id = ?', [$domainId]);
        }
        return $this->db->row(
            'SELECT * FROM domains WHERE id = ? AND id = ?',
            [$domainId, $actor['domain_id'] ?? null]
        );
    }
}
