<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\BackupRepository;
use App\Repository\CertificateRepository;
use App\Repository\DatabaseRepository;
use App\Repository\DomainRepository;
use App\Repository\SiteRepository;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;

/**
 * Plan-based quota enforcement shared by the module services.
 */
final class QuotaService
{
    private UserRepository $users;

    private DomainRepository $domains;

    private SiteRepository $sites;

    private DatabaseRepository $databases;

    public function __construct()
    {
        $this->users = new UserRepository();
        $this->domains = new DomainRepository();
        $this->sites = new SiteRepository();
        $this->databases = new DatabaseRepository();
    }

    public function limits(array $user): array
    {
        $plan = $user['plan_id'] ? $this->findPlan((int) $user['plan_id']) : null;

        return [
            'max_domains' => $plan ? (int) $plan['max_domains'] : 5,
            'max_sites' => $plan ? (int) $plan['max_sites'] : 5,
            'max_databases' => $plan ? (int) $plan['max_databases'] : 5,
            'disk_quota' => $plan ? (int) $plan['disk_quota'] : 1024,
            'bandwidth_quota' => $plan ? (int) $plan['bandwidth_quota'] : 1024,
        ];
    }

    public function usage(array $user): array
    {
        return [
            'domains' => $this->domains->countForUser((int) $user['id']),
            'sites' => $this->sites->countForUser((int) $user['id']),
            'databases' => $this->databases->countForUser((int) $user['id']),
        ];
    }

    public function usageWithEntities(array $user): array
    {
        $usage = $this->usage($user);
        $usage['backups'] = (new BackupRepository())->countForUser((int) $user['id']);
        $usage['certificates'] = (new CertificateRepository())->countForUser((int) $user['id']);
        $usage['tasks'] = (new TaskRepository())->countForUser((int) $user['id']);

        return $usage;
    }

    private function findPlan(int $planId): ?array
    {
        $stmt = (new \App\Repository\PlanRepository())->findById($planId);

        return $stmt;
    }
}
