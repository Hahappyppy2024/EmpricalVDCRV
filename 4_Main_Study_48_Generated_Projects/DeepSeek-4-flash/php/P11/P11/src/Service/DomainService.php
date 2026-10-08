<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\AuditRepository;
use App\Repository\DomainRepository;
use App\Support\Validation;

final class DomainService
{
    private DomainRepository $domains;

    private AuditRepository $audit;

    private QuotaService $quota;

    public function __construct()
    {
        $this->domains = new DomainRepository();
        $this->audit = new AuditRepository();
        $this->quota = new QuotaService();
    }

    public function listFor(array $user): array
    {
        $domains = $this->domains->allForUser((int) $user['id']);
        foreach ($domains as &$domain) {
            $domain['dns_count'] = count($this->domains->dnsRecords((int) $domain['id']));
        }

        return $domains;
    }

    public function listForActor(array $user): array
    {
        if ($user['role'] === 'admin') {
            return $this->domains->all();
        }

        return $this->listFor($user);
    }

    public function listAll(): array
    {
        return $this->domains->all();
    }

    public function getForActor(array $user, int $id): ?array
    {
        if ($user['role'] === 'admin') {
            $domain = $this->domains->find($id);
            if ($domain !== null) {
                $domain['dns_records'] = $this->domains->dnsRecords($id);
            }

            return $domain;
        }

        return $this->getForUser($id, $user);
    }

    public function getForUser(int $id, array $user): ?array
    {
        $domain = $this->domains->findForUser($id, (int) $user['id']);
        if ($domain === null) {
            return null;
        }
        $domain['dns_records'] = $this->domains->dnsRecords($id);

        return $domain;
    }

    /**
     * @return array{0: ?string, 1: ?int} [error, id]
     */
    public function create(array $user, string $name, string $kind, string $status): array
    {
        $name = strtolower(trim($name));
        if ($name === '') {
            return ['Domain name is required.', null];
        }
        if (!in_array($kind, ['domain', 'subdomain', 'alias'], true)) {
            return ['Invalid domain kind.', null];
        }
        if (!in_array($status, ['active', 'suspended'], true)) {
            return ['Invalid domain status.', null];
        }

        $limits = $this->quota->limits($user);
        if ($this->domains->countForUser((int) $user['id']) >= $limits['max_domains']) {
            return ['Domain quota exceeded for your plan.', null];
        }
        if ($this->domains->nameExistsForUser($name, (int) $user['id'])) {
            return ['This domain already exists for your account.', null];
        }

        $id = $this->domains->create((int) $user['id'], $name, $kind, $status);
        $this->audit->record((int) $user['id'], $user['username'], 'create', 'domain_management', 'domain', (string) $id, 'Added ' . $kind . ' ' . $name);

        return [null, $id];
    }

    /**
     * @return array{0: ?string} [error]
     */
    public function update(array $user, int $id, string $name, string $kind, string $status): array
    {
        $domain = $this->domains->findForUser($id, (int) $user['id']);
        if ($domain === null) {
            return ['Unknown or out-of-scope domain.'];
        }

        $name = strtolower(trim($name));
        if ($name === '') {
            return ['Domain name is required.'];
        }
        if (!in_array($kind, ['domain', 'subdomain', 'alias'], true)) {
            return ['Invalid domain kind.'];
        }
        if (!in_array($status, ['active', 'suspended'], true)) {
            return ['Invalid domain status.'];
        }
        if ($this->domains->nameExistsForUser($name, (int) $user['id'], $id)) {
            return ['This domain already exists for your account.'];
        }

        $this->domains->update($id, $name, $kind, $status);
        $this->audit->record((int) $user['id'], $user['username'], 'update', 'domain_management', 'domain', (string) $id, 'Updated ' . $name);

        return [null];
    }

    public function delete(array $user, int $id): ?string
    {
        $domain = $this->domains->findForUser($id, (int) $user['id']);
        if ($domain === null) {
            return 'Unknown or out-of-scope domain.';
        }
        $this->domains->delete($id);
        $this->audit->record((int) $user['id'], $user['username'], 'delete', 'domain_management', 'domain', (string) $id, 'Deleted ' . $domain['name']);

        return null;
    }

    public function updateStatusActor(array $user, int $id, string $status): ?string
    {
        $domain = $user['role'] === 'admin' ? $this->domains->find($id) : $this->domains->findForUser($id, (int) $user['id']);
        if ($domain === null) {
            return 'Unknown or out-of-scope domain.';
        }
        if (!in_array($status, ['active', 'suspended'], true)) {
            return 'Invalid domain status.';
        }
        $this->domains->update($id, $domain['name'], $domain['kind'], $status);
        $this->audit->record((int) $user['id'], $user['username'], 'update', 'domain_management', 'domain', (string) $id, 'Set status ' . $status . ' for ' . $domain['name']);

        return null;
    }

    public function deleteActor(array $user, int $id): ?string
    {
        $domain = $user['role'] === 'admin' ? $this->domains->find($id) : $this->domains->findForUser($id, (int) $user['id']);
        if ($domain === null) {
            return 'Unknown or out-of-scope domain.';
        }
        $this->domains->delete($id);
        $this->audit->record((int) $user['id'], $user['username'], 'delete', 'domain_management', 'domain', (string) $id, 'Deleted ' . $domain['name']);

        return null;
    }

    /**
     * @return array{0: ?string, 1: ?int} [error, dnsId]
     */
    public function addDns(array $user, int $domainId, string $type, string $dnsName, string $value, int $ttl): array
    {
        $domain = $this->domains->findForUser($domainId, (int) $user['id']);
        if ($domain === null) {
            return ['Unknown or out-of-scope domain.', null];
        }
        $type = strtoupper(trim($type));
        if (!in_array($type, ['A', 'AAAA', 'CNAME', 'MX', 'TXT'], true)) {
            return ['Unsupported DNS record type.', null];
        }
        if (trim($dnsName) === '' || trim($value) === '') {
            return ['DNS name and value are required.', null];
        }
        $id = $this->domains->addDns($domainId, $type, trim($dnsName), trim($value), max(60, $ttl));
        $this->audit->record((int) $user['id'], $user['username'], 'create', 'domain_management', 'dns_record', (string) $id, 'Added DNS ' . $type . ' record');

        return [null, $id];
    }

    public function deleteDns(array $user, int $domainId, int $dnsId): ?string
    {
        $domain = $this->domains->findForUser($domainId, (int) $user['id']);
        if ($domain === null) {
            return 'Unknown or out-of-scope domain.';
        }
        if (!$this->domains->deleteDns($dnsId, $domainId)) {
            return 'Unknown DNS record.';
        }
        $this->audit->record((int) $user['id'], $user['username'], 'delete', 'domain_management', 'dns_record', (string) $dnsId, 'Deleted DNS record');

        return null;
    }
}
