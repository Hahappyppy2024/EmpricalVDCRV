<?php

declare(strict_types=1);

namespace App\Database;

use PDO;

/**
 * Deterministic seed fixtures for every actor, role, entity, relationship and
 * workflow state required by the acceptance criteria. Safe to run repeatedly:
 * it seeds only when no users exist yet.
 */
final class Seeder
{
    private PDO $db;

    private string $storageDir;

    public function __construct(string $storageDir)
    {
        $this->db = Connection::db();
        $this->storageDir = rtrim($storageDir, '/\\');
    }

    public function seed(): void
    {
        Schema::ensure();

        $count = (int) $this->db->query('SELECT COUNT(*) FROM users')->fetchColumn();
        if ($count > 0) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        $this->db->exec('BEGIN TRANSACTION');

        $plans = [
            ['Free', 100, 512, 2, 1, 1, 0.0],
            ['Basic', 1024, 10240, 5, 5, 5, 9.99],
            ['Pro', 5120, 102400, 20, 20, 20, 29.99],
        ];
        $planIds = [];
        $insertPlan = $this->db->prepare(
            'INSERT INTO plans (name, disk_quota, bandwidth_quota, max_domains, max_sites, max_databases, monthly_price, created_at) VALUES (?,?,?,?,?,?,?,?)'
        );
        foreach ($plans as $p) {
            $insertPlan->execute([$p[0], $p[1], $p[2], $p[3], $p[4], $p[5], $p[6], $now]);
            $planIds[$p[0]] = (int) $this->db->lastInsertId();
        }

        $insertUser = $this->db->prepare(
            'INSERT INTO users (username, email, password_hash, full_name, role, plan_id, created_at) VALUES (?,?,?,?,?,?,?)'
        );
        $adminId = $this->insertUser($insertUser, 'admin', 'admin@example.com', 'Admin@123', 'Administrator', 'admin', $planIds['Pro'], $now);
        $supportId = $this->insertUser($insertUser, 'support', 'support@example.com', 'Support@123', 'Support Agent', 'support', $planIds['Pro'], $now);
        $aliceId = $this->insertUser($insertUser, 'alice', 'alice@example.com', 'Alice@123', 'Alice Customer', 'customer', $planIds['Basic'], $now);
        $bobId = $this->insertUser($insertUser, 'bob', 'bob@example.com', 'Bob@123', 'Bob Customer', 'customer', $planIds['Basic'], $now);

        // ---- Alice's hosted objects -----------------------------------
        $aliceDomain1 = $this->insertDomain($aliceId, 'alice.example.com', 'domain', 'active', $now);
        $aliceDomain2 = $this->insertDomain($aliceId, 'www.alice.example.com', 'subdomain', 'active', $now);
        $aliceDomain3 = $this->insertDomain($aliceId, 'shop.alice.example.com', 'subdomain', 'active', $now);
        $this->insertAlias($aliceId, 'alice-example.net', 'active', $now);

        $this->insertDns($aliceDomain1, 'A', '@', '192.0.2.10', 3600);
        $this->insertDns($aliceDomain1, 'MX', '@', 'mail.alice.example.com', 3600);
        $this->insertDns($aliceDomain1, 'TXT', '@', 'v=spf1 include:_spf.example.com ~all', 3600);
        $this->insertDns($aliceDomain2, 'CNAME', 'www', 'alice.example.com', 3600);
        $this->insertDns($aliceDomain3, 'A', 'shop', '192.0.2.20', 3600);

        $aliceSite1 = $this->insertSite($aliceId, $aliceDomain1, 'alice-main', '/var/www/alice/htdocs', 'deployed', $now, $now);
        $aliceSite2 = $this->insertSite($aliceId, $aliceDomain3, 'alice-shop', '/var/www/alice/shop', 'deployed', $now, $now);

        $this->writeSeedFile($aliceId, $aliceSite1, 'index.html', 'text/html', "<!DOCTYPE html>\n<html><body><h1>Alice Main Site</h1></body></html>\n", $now);
        $this->writeSeedFile($aliceId, $aliceSite1, 'robots.txt', 'text/plain', "User-agent: *\nAllow: /\n", $now);
        $this->writeSeedFile($aliceId, $aliceSite1, 'assets/logo.png', 'image/png', "PNG-SEED", $now);
        $this->writeSeedFile($aliceId, $aliceSite2, 'index.php', 'text/plain', "<?php echo 'shop';\n", $now);

        $aliceDb1 = $this->insertDatabase($aliceId, 'alice_wp', $now);
        $aliceDb2 = $this->insertDatabase($aliceId, 'alice_shop', $now);
        $this->insertDbUser($aliceDb1, 'alice_wp_user', 'DbPass@123', 'localhost', 'ALL');
        $this->insertDbUser($aliceDb2, 'alice_shop_user', 'DbPass@456', 'localhost', 'SELECT,INSERT,UPDATE');

        $this->writeSeedBackup($aliceId, 'alice-main-before-upgrade', 'site', (int) $aliceSite1['id'], $now);
        $this->writeSeedBackup($aliceId, 'alice-wp-full', 'database', (int) $aliceDb1, $now);

        $this->insertCertificate($aliceId, $aliceDomain1, 'letsencrypt', 'active', '2026-01-01 00:00:00', '2026-10-01 23:59:59', $now, $now);

        $this->insertTask($aliceId, 'Daily site backup', 'backup --site alice-main', '0 2 * * *', 1, 'idle', $now, '2026-08-20 02:00:00', 'Backup completed OK', $now);
        $this->insertTask($aliceId, 'Log rotation', 'logrotate -f /etc/logrotate.d/alice', '0 3 * * *', 1, 'idle', $now, '2026-08-20 03:00:00', '', $now);
        $this->insertTask($aliceId, 'Uptime check', 'curl -fsS https://alice.example.com', '*/15 * * * *', 0, 'idle', $now, null, '', $now);

        $this->seedUsage($aliceId, $now);
        $this->seedUsage($bobId, $now);

        $this->insertTicket($aliceId, 'Cannot renew SSL certificate', 'open', $now, [
            [$aliceId, 'customer', 'My SSL certificate is stuck in requested state, please check.', $now],
        ]);
        $this->insertTicket($aliceId, 'Increase PHP memory limit', 'answered', $now, [
            [$aliceId, 'customer', 'Please raise memory_limit for my site to 256M.', $now],
            [$supportId, 'support', 'Done. memory_limit is now 256M for alice-main.', $now],
        ]);

        // ---- Bob's hosted objects --------------------------------------
        $bobDomain = $this->insertDomain($bobId, 'bob.example.com', 'domain', 'active', $now);
        $bobSite = $this->insertSite($bobId, $bobDomain, 'bob-blog', '/var/www/bob/htdocs', 'deployed', $now, $now);
        $this->writeSeedFile($bobId, $bobSite, 'index.html', 'text/html', "<!DOCTYPE html>\n<html><body><h1>Bob Blog</h1></body></html>\n", $now);
        $bobDb = $this->insertDatabase($bobId, 'bob_blog', $now);
        $this->insertDbUser($bobDb, 'bob_blog_user', 'BobDb@123', 'localhost', 'ALL');
        $this->insertTicket($bobId, 'FTP account broken', 'closed', $now, [
            [$bobId, 'customer', 'My FTP account stopped working after last maintenance.', $now],
            [$supportId, 'support', 'Recreated the FTP user, please retry.', $now],
            [$bobId, 'customer', 'Works now, thanks.', $now],
        ]);

        // ---- Audit history and settings --------------------------------
        $this->insertAudit($adminId, 'admin', 'configure', 'admin_operations', 'setting', 'maintenance_mode', 'Maintenance mode enabled for scheduled upgrade', $now);
        $this->insertAudit($aliceId, 'alice', 'create', 'domain_management', 'domain', (string) $aliceDomain1, 'Added domain alice.example.com', $now);
        $this->insertAudit($aliceId, 'alice', 'create', 'database_management', 'database', (string) $aliceDb1, 'Created database alice_wp', $now);
        $this->insertAudit($aliceId, 'alice', 'request', 'ssl_certificate_management', 'certificate', '1', 'Requested certificate for alice.example.com', $now);
        $this->insertAudit($supportId, 'support', 'reply', 'support_tickets', 'ticket', '2', 'Answered ticket 2', $now);
        $this->insertAudit($bobId, 'bob', 'create', 'site_management', 'site', (string) $bobSite['id'], 'Deployed site bob-blog', $now);

        $this->setSetting('panel_name', 'P11 Hosting Control Panel');
        $this->setSetting('maintenance_mode', 'off');
        $this->setSetting('default_plan', 'Free');
        $this->setSetting('support_email', 'support@example.com');
        $this->setSetting('backup_retention_days', '30');

        $this->db->exec('COMMIT');
    }

    private function insertUser(\PDOStatement $stmt, string $username, string $email, string $password, string $fullName, string $role, int $planId, string $now): int
    {
        $stmt->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $fullName, $role, $planId, $now]);

        return (int) $this->db->lastInsertId();
    }

    private function insertDomain(int $userId, string $name, string $kind, string $status, string $now): int
    {
        $stmt = $this->db->prepare('INSERT INTO domains (user_id, name, kind, status, created_at) VALUES (?,?,?,?,?)');
        $stmt->execute([$userId, $name, $kind, $status, $now]);

        return (int) $this->db->lastInsertId();
    }

    private function insertAlias(int $userId, string $name, string $status, string $now): int
    {
        return $this->insertDomain($userId, $name, 'alias', $status, $now);
    }

    private function insertDns(int $domainId, string $type, string $name, string $value, int $ttl): void
    {
        $stmt = $this->db->prepare('INSERT INTO dns_records (domain_id, type, name, value, ttl) VALUES (?,?,?,?,?)');
        $stmt->execute([$domainId, $type, $name, $value, $ttl]);
    }

    private function insertSite(int $userId, int $domainId, string $name, string $documentRoot, string $status, string $now, string $deployedAt): array
    {
        $stmt = $this->db->prepare('INSERT INTO sites (user_id, domain_id, name, document_root, status, deployed_at, created_at) VALUES (?,?,?,?,?,?,?)');
        $stmt->execute([$userId, $domainId, $name, $documentRoot, $status, $deployedAt, $now]);
        $id = (int) $this->db->lastInsertId();
        $stmt = $this->db->prepare('SELECT * FROM sites WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch();
    }

    private function writeSeedFile(int $userId, array $site, string $path, string $mime, string $content, string $now): void
    {
        $siteRoot = $this->storageDir . '/files/' . $userId . '/' . $site['name'];
        if (!is_dir($siteRoot)) {
            mkdir($siteRoot, 0777, true);
        }
        $target = $siteRoot . '/' . str_replace('\\', '/', $path);
        $targetDir = dirname($target);
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        file_put_contents($target, $content);

        $recordPath = $site['name'] . '/' . ltrim(str_replace('\\', '/', $path), '/');
        $stmt = $this->db->prepare(
            'INSERT INTO stored_files (user_id, site_id, file_path, file_name, file_size, mime_type, is_dir, created_at, updated_at) VALUES (?,?,?,?,?,?,0,?,?)'
        );
        $stmt->execute([$userId, $site['id'], $recordPath, basename($recordPath), strlen($content), $mime, $now, $now]);
    }

    private function insertDatabase(int $userId, string $name, string $now): int
    {
        $stmt = $this->db->prepare('INSERT INTO databases (user_id, name, created_at) VALUES (?,?,?)');
        $stmt->execute([$userId, $name, $now]);

        return (int) $this->db->lastInsertId();
    }

    private function insertDbUser(int $databaseId, string $username, string $password, string $host, string $privileges): void
    {
        $stmt = $this->db->prepare('INSERT INTO database_users (database_id, username, password_hash, host, privileges) VALUES (?,?,?,?,?)');
        $stmt->execute([$databaseId, $username, password_hash($password, PASSWORD_DEFAULT), $host, $privileges]);
    }

    private function writeSeedBackup(int $userId, string $name, string $sourceType, int $sourceId, string $now): void
    {
        $dir = $this->storageDir . '/backups/' . $userId;
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $fileName = preg_replace('/[^A-Za-z0-9_-]/', '_', $name) . '.backup';
        $target = $dir . '/' . $fileName;
        $payload = json_encode([
            'name' => $name,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'user_id' => $userId,
            'created_at' => $now,
            'content' => 'SEED_BACKUP',
        ], JSON_PRETTY_PRINT);
        file_put_contents($target, $payload);

        $stmt = $this->db->prepare(
            'INSERT INTO backups (user_id, name, kind, source_type, source_id, stored_path, file_size, status, created_at) VALUES (?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([$userId, $name, $sourceType, $sourceType, $sourceId, $target, strlen($payload), 'ready', $now]);
    }

    private function insertCertificate(int $userId, int $domainId, string $provider, string $status, string $notBefore, string $notAfter, string $now, string $renewedAt): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO certificates (user_id, domain_id, provider, status, certificate_text, private_key_text, not_before, not_after, created_at, renewed_at) VALUES (?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([$userId, $domainId, $provider, $status, "-----BEGIN CERTIFICATE-----\nSEED\n-----END CERTIFICATE-----", "-----BEGIN PRIVATE KEY-----\nSEED\n-----END PRIVATE KEY-----", $notBefore, $notAfter, $now, $renewedAt]);
    }

    private function insertTask(int $userId, string $name, string $command, string $schedule, int $enabled, string $status, string $now, ?string $nextRun, string $lastOutput, string $lastRun): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO scheduled_tasks (user_id, name, command, schedule, enabled, status, last_run, next_run, last_output, created_at) VALUES (?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([$userId, $name, $command, $schedule, $enabled, $status, $lastRun, $nextRun, $lastOutput, $now]);
    }

    private function seedUsage(int $userId, string $now): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO resource_usage (user_id, recorded_at, cpu_usage, disk_used, traffic_used, quota_percent) VALUES (?,?,?,?,?,?)'
        );
        for ($i = 13; $i >= 0; $i--) {
            $recorded = date('Y-m-d H:i:s', strtotime($now . ' -' . $i . ' days'));
            $cpu = 10.0 + ((int) ($i * 2.7) % 35);
            $disk = 120 + $i * 9;
            $traffic = 300 + $i * 22;
            $stmt->execute([$userId, $recorded, round($cpu, 2), $disk, $traffic, round(($disk / 1024) * 100, 2)]);
        }
    }

    private function insertTicket(int $userId, string $subject, string $status, string $now, array $messages): void
    {
        $stmt = $this->db->prepare('INSERT INTO tickets (user_id, subject, status, created_at) VALUES (?,?,?,?)');
        $stmt->execute([$userId, $subject, $status, $now]);
        $ticketId = (int) $this->db->lastInsertId();

        $msg = $this->db->prepare('INSERT INTO ticket_messages (ticket_id, author_id, author_role, body, created_at) VALUES (?,?,?,?,?)');
        foreach ($messages as $m) {
            $msg->execute([$ticketId, $m[0], $m[1], $m[2], $m[3]]);
        }
    }

    private function insertAudit(int $userId, string $username, string $action, string $module, string $entityType, string $entityId, string $details, string $now): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO audit_events (user_id, username, action, module, entity_type, entity_id, details, created_at) VALUES (?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([$userId, $username, $action, $module, $entityType, $entityId, $details, $now]);
    }

    private function setSetting(string $key, string $value): void
    {
        $stmt = $this->db->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (?,?)');
        $stmt->execute([$key, $value]);
    }
}
