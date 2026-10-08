<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\FileRepository;
use App\Repository\UsageRepository;
use App\Repository\UserRepository;

final class ResourceUsageService
{
    private UsageRepository $usage;

    private FileRepository $files;

    private UserRepository $users;

    private QuotaService $quota;

    public function __construct()
    {
        $this->usage = new UsageRepository();
        $this->files = new FileRepository();
        $this->users = new UserRepository();
        $this->quota = new QuotaService();
    }

    public function dashboard(array $user): array
    {
        $userId = (int) $user['id'];
        $limits = $this->quota->limits($user);
        $usage = $this->quota->usageWithEntities($user);

        $latest = $this->usage->latestForUser($userId);
        if ($latest === null) {
            $this->usage->record($userId, 0.0, 0, 0, 0.0);
            $latest = $this->usage->latestForUser($userId);
        }

        $diskUsed = $this->files->totalSizeForUser($userId) / 1024 / 1024;

        return [
            'limits' => $limits,
            'usage' => $usage,
            'disk_used_mb' => round($diskUsed, 2),
            'disk_quota_mb' => (int) $limits['disk_quota'],
            'latest' => $latest,
            'history' => $this->usage->historyForUser($userId, 30),
        ];
    }

    public function usageForCustomer(array $user): array
    {
        return $this->dashboard($user);
    }

    /**
     * Usage overview across all customers, used by support staff.
     */
    public function overview(): array
    {
        $customers = [];
        foreach ($this->users->all() as $u) {
            if ($u['role'] === 'customer') {
                $customers[] = $u;
            }
        }
        $result = [];
        foreach ($customers as $c) {
            $latest = $this->usage->latestForUser((int) $c['id']);
            $result[] = [
                'id' => (int) $c['id'],
                'username' => $c['username'],
                'email' => $c['email'],
                'plan_name' => $c['plan_name'] ?? 'Free',
                'disk_used' => (int) ($latest['disk_used'] ?? 0),
                'traffic_used' => (int) ($latest['traffic_used'] ?? 0),
                'quota_percent' => (float) ($latest['quota_percent'] ?? 0),
                'cpu_usage' => (float) ($latest['cpu_usage'] ?? 0),
                'domains' => (int) $c['domains_count'],
                'sites' => (int) $c['sites_count'],
                'databases' => (int) $c['databases_count'],
            ];
        }

        return $result;
    }
}
