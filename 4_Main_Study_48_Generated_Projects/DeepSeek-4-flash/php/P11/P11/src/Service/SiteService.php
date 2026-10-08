<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\AuditRepository;
use App\Repository\DomainRepository;
use App\Repository\SiteRepository;

final class SiteService
{
    private SiteRepository $sites;

    private DomainRepository $domains;

    private AuditRepository $audit;

    private QuotaService $quota;

    public function __construct()
    {
        $this->sites = new SiteRepository();
        $this->domains = new DomainRepository();
        $this->audit = new AuditRepository();
        $this->quota = new QuotaService();
    }

    public function listFor(array $user): array
    {
        return $this->sites->allForUser((int) $user['id']);
    }

    public function getForUser(int $id, array $user): ?array
    {
        return $this->sites->findForUser($id, (int) $user['id']);
    }

    public function domainsFor(array $user): array
    {
        return $this->domains->allForUser((int) $user['id']);
    }

    /**
     * @return array{0: ?string, 1: ?int} [error, id]
     */
    public function create(array $user, int $domainId, string $name, string $documentRoot, string $status): array
    {
        $name = trim($name);
        $documentRoot = trim($documentRoot);
        if ($name === '') {
            return ['Site name is required.', null];
        }
        if ($documentRoot === '') {
            return ['Document root is required.', null];
        }
        if (!in_array($status, ['pending', 'deployed', 'failed'], true)) {
            return ['Invalid site status.', null];
        }
        $domain = $this->domains->findForUser($domainId, (int) $user['id']);
        if ($domain === null) {
            return ['Selected domain does not exist for your account.', null];
        }
        $limits = $this->quota->limits($user);
        if ($this->sites->countForUser((int) $user['id']) >= $limits['max_sites']) {
            return ['Site quota exceeded for your plan.', null];
        }
        if ($this->sites->nameExistsForUser($name, (int) $user['id'])) {
            return ['A site with this name already exists.', null];
        }

        $id = $this->sites->create((int) $user['id'], $domainId, $name, $documentRoot, $status);
        $siteDir = \App\Support\Storage::filesDir((int) $user['id']) . '/' . $name;
        if (!is_dir($siteDir)) {
            mkdir($siteDir, 0777, true);
        }
        $this->audit->record((int) $user['id'], $user['username'], 'create', 'site_management', 'site', (string) $id, 'Created site ' . $name . ' at ' . $documentRoot);

        return [null, $id];
    }

    /**
     * @return array{0: ?string} [error]
     */
    public function update(array $user, int $id, int $domainId, string $name, string $documentRoot, string $status): array
    {
        $site = $this->sites->findForUser($id, (int) $user['id']);
        if ($site === null) {
            return ['Unknown or out-of-scope site.'];
        }
        $name = trim($name);
        $documentRoot = trim($documentRoot);
        if ($name === '') {
            return ['Site name is required.'];
        }
        if ($documentRoot === '') {
            return ['Document root is required.'];
        }
        if (!in_array($status, ['pending', 'deployed', 'failed'], true)) {
            return ['Invalid site status.'];
        }
        $domain = $this->domains->findForUser($domainId, (int) $user['id']);
        if ($domain === null) {
            return ['Selected domain does not exist for your account.'];
        }
        if ($this->sites->nameExistsForUser($name, (int) $user['id'], $id)) {
            return ['A site with this name already exists.'];
        }

        $this->sites->update($id, $domainId, $name, $documentRoot, $status);
        $this->audit->record((int) $user['id'], $user['username'], 'update', 'site_management', 'site', (string) $id, 'Updated site ' . $name);

        return [null];
    }

    public function deploy(array $user, int $id): ?string
    {
        $site = $this->sites->findForUser($id, (int) $user['id']);
        if ($site === null) {
            return 'Unknown or out-of-scope site.';
        }
        $this->sites->setStatus($id, 'deployed', date('Y-m-d H:i:s'));
        $this->audit->record((int) $user['id'], $user['username'], 'deploy', 'site_management', 'site', (string) $id, 'Deployed ' . $site['name']);

        return null;
    }
}
