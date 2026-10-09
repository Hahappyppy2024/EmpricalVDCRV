<?php

declare(strict_types=1);

namespace App\Seed;

use App\Database\Connection;

final class Seeder
{
    /** @var array<string,mixed> */
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function run(): void
    {
        $pdo = Connection::get();
        $pdo->beginTransaction();

        $this->seedUsers();
        $this->seedServices();
        $this->seedJobProfiles();
        $this->seedMetrics();
        $this->seedLogFiles();
        $this->seedAlerts();
        $this->seedHealthCheckTargets();
        $this->seedConfigurationKeys();
        $this->seedApiTokens();
        $this->seedAuditEvents();
        $this->seedScheduledJobsAndRuns();

        $pdo->commit();
    }

    private function seedUsers(): void
    {
        $pdo = Connection::get();
        $admin = [
            'username' => (string)$this->config['seed.admin_username'],
            'email' => 'admin@p16.local',
            'password' => (string)$this->config['seed.admin_password'],
            'role' => 'admin',
            'full_name' => 'Ada Admin',
        ];
        $operator = [
            'username' => (string)$this->config['seed.operator_username'],
            'email' => 'operator@p16.local',
            'password' => (string)$this->config['seed.operator_password'],
            'role' => 'operator',
            'full_name' => 'Olivia Operator',
        ];
        $extra = [
            ['username' => 'op2', 'email' => 'op2@p16.local', 'password' => 'Op2#12345', 'role' => 'operator', 'full_name' => 'Oren Onboarding'],
            ['username' => 'viewer', 'email' => 'viewer@p16.local', 'password' => 'Viewer#12345', 'role' => 'operator', 'full_name' => 'Vera Viewer'],
        ];
        $stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash, role, full_name) VALUES (:u,:e,:p,:r,:n)');
        foreach ([$admin, $operator, ...$extra] as $u) {
            $stmt->execute([
                ':u' => $u['username'],
                ':e' => $u['email'],
                ':p' => password_hash($u['password'], PASSWORD_DEFAULT),
                ':r' => $u['role'],
                ':n' => $u['full_name'],
            ]);
        }
    }

    private function seedServices(): void
    {
        $rows = [
            ['name' => 'web-frontend',  'description' => 'Primary HTTP frontend service', 'state' => 'running'],
            ['name' => 'metric-collector', 'description' => 'Pulls metrics from devices', 'state' => 'running'],
            ['name' => 'alert-engine',  'description' => 'Background alert processor', 'state' => 'stopped'],
            ['name' => 'ingest-queue',  'description' => 'Queue for incoming telemetry', 'state' => 'running'],
            ['name' => 'report-builder', 'description' => 'Generates weekly reports', 'state' => 'stopped'],
        ];
        $stmt = Connection::get()->prepare(
            'INSERT INTO services (name, description, state, pid, started_at) VALUES (:n,:d, :s, :p, :start)'
        );
        foreach ($rows as $r) {
            $stmt->execute([
                ':n' => $r['name'],
                ':d' => $r['description'],
                ':s' => $r['state'],
                ':p' => $r['state'] === 'running' ? random_int(200, 60000) : 0,
                ':start' => $r['state'] === 'running'
                    ? (new \DateTimeImmutable('-' . random_int(1, 8) . ' hours'))->format('Y-m-d H:i:s')
                    : null,
            ]);
        }
    }

    private function seedJobProfiles(): void
    {
        $rows = [
            ['code' => 'metric_collector', 'name' => 'Run metric collector', 'description' => 'Pulls metrics from local devices.', 'command' => 'php bin/seed.php --profile=metric'],
            ['code' => 'report_export', 'name' => 'Export weekly report', 'description' => 'Generates weekly monitoring report.', 'command' => 'php bin/seed.php --profile=report'],
            ['code' => 'db_vacuum', 'name' => 'Vacuum local SQLite', 'description' => 'Runs SQLite VACUUM on the local DB.', 'command' => 'php bin/seed.php --profile=vacuum'],
            ['code' => 'alert_summarize', 'name' => 'Summarize open alerts', 'description' => 'Builds an alert summary document.', 'command' => 'php bin/seed.php --profile=alerts'],
            ['code' => 'backup_local', 'name' => 'Snapshot config + logs', 'description' => 'Bundles config keys and log entries.', 'command' => 'php bin/seed.php --profile=backup'],
        ];
        $stmt = Connection::get()->prepare(
            'INSERT INTO job_profiles (code, name, description, command) VALUES (:c,:n,:d,:cmd)'
        );
        foreach ($rows as $r) {
            $stmt->execute([':c' => $r['code'], ':n' => $r['name'], ':d' => $r['description'], ':cmd' => $r['command']]);
        }
    }

    private function seedMetrics(): void
    {
        $repo = new \App\Repositories\MetricSnapshotRepository();
        $repo->generateDeterministicSeries('localhost');
        $repo->generateDeterministicSeries('edge-01');
    }

    private function seedLogFiles(): void
    {
        $pdo = Connection::get();
        $files = [
            ['name' => 'app.log', 'path' => 'logs/app.log', 'size' => 18432, 'source' => 'application', 'description' => 'Main application log.'],
            ['name' => 'errors.log', 'path' => 'logs/errors.log', 'size' => 4096, 'source' => 'system', 'description' => 'Error-level events only.'],
            ['name' => 'jobs.log', 'path' => 'logs/jobs.log', 'size' => 8192, 'source' => 'jobs', 'description' => 'Scheduled job executions.'],
            ['name' => 'access.log', 'path' => 'logs/access.log', 'size' => 12288, 'source' => 'web', 'description' => 'Inbound HTTP requests.'],
        ];
        $stmt = $pdo->prepare(
            'INSERT INTO log_files (name, path, size_bytes, source, description) VALUES (:n,:p,:s,:src,:d)'
        );
        $entries = [];
        $msgTemplates = [
            'info' => ['User login succeeded', 'Background job queued', 'Health check executed', 'Configuration updated', 'Service restarted'],
            'warning' => ['Slow query detected', 'Disk usage above threshold', 'Failed login attempt', 'Queue depth growing'],
            'error' => ['Connection refused to upstream', 'Database write failed', 'Job exited with non-zero status'],
        ];
        foreach ($files as $f) {
            $stmt->execute([':n' => $f['name'], ':p' => $f['path'], ':s' => $f['size'], ':src' => $f['source'], ':d' => $f['description']]);
            $fid = (int)$pdo->lastInsertId();
            $base = new \DateTimeImmutable('-3 hours');
            for ($i = 0; $i < 40; $i++) {
                $level = ['info', 'info', 'info', 'warning', 'error'][$i % 5];
                $msg = $msgTemplates[$level][($i + $fid) % count($msgTemplates[$level])];
                $ts = $base->modify('+' . $i * 4 . ' minutes')->format('Y-m-d H:i:s');
                $entries[] = [
                    'file_id' => $fid,
                    'ts' => $ts,
                    'level' => $level,
                    'message' => $msg . ' #' . ($i + $fid),
                ];
            }
        }
        $entryStmt = $pdo->prepare(
            'INSERT INTO log_entries (log_file_id, ts, level, message, context) VALUES (:f,:t,:l,:m,:c)'
        );
        foreach ($entries as $e) {
            $entryStmt->execute([
                ':f' => $e['file_id'],
                ':t' => $e['ts'],
                ':l' => $e['level'],
                ':m' => $e['message'],
                ':c' => json_encode(['seed' => true]),
            ]);
        }
    }

    private function seedAlerts(): void
    {
        $pdo = Connection::get();
        $alerts = [
            ['severity' => 'critical', 'title' => 'Database write failure', 'message' => 'Insert into metrics snapshot failed.', 'source' => 'ingest', 'state' => 'open'],
            ['severity' => 'warning',  'title' => 'Disk above 80%', 'message' => '/var/lib reached 82% capacity.', 'source' => 'system', 'state' => 'open'],
            ['severity' => 'info',     'title' => 'Service restart completed', 'message' => 'web-frontend restarted.', 'source' => 'service', 'state' => 'acknowledged'],
            ['severity' => 'critical', 'title' => 'Heartbeat lost', 'message' => 'edge-01 missed 3 heartbeats.', 'source' => 'health', 'state' => 'assigned'],
        ];
        $stmt = $pdo->prepare(
            'INSERT INTO alerts (severity, title, message, source, state, assignee_id, acknowledged_by, acknowledged_at)
             VALUES (:s,:t,:m,:src,:state,:asg,:ackb,:ackt)'
        );
        foreach ($alerts as $i => $a) {
            $stmt->execute([
                ':s' => $a['severity'],
                ':t' => $a['title'],
                ':m' => $a['message'],
                ':src' => $a['source'],
                ':state' => $a['state'],
                ':asg' => $a['state'] === 'assigned' ? 3 : null,
                ':ackb' => $a['state'] === 'acknowledged' ? 1 : null,
                ':ackt' => $a['state'] === 'acknowledged' ? (new \DateTimeImmutable('-15 minutes'))->format('Y-m-d H:i:s') : null,
            ]);
        }
    }

    private function seedHealthCheckTargets(): void
    {
        $pdo = Connection::get();
        $rows = [
            ['owner' => 2, 'name' => 'Local web', 'kind' => 'http', 'target' => 'http://localhost:8080/healthz', 'interval' => 60, 'timeout' => 1000],
            ['owner' => 2, 'name' => 'Local TCP probe', 'kind' => 'tcp', 'target' => '127.0.0.1:8080', 'interval' => 90, 'timeout' => 2000],
            ['owner' => 2, 'name' => 'Edge device heartbeat', 'kind' => 'script', 'target' => 'check-edge-heartbeat.sh', 'interval' => 120, 'timeout' => 3000],
        ];
        $stmt = $pdo->prepare(
            'INSERT INTO health_check_targets (owner_id, name, kind, target, interval_sec, timeout_ms, state, last_check_at, last_error)
             VALUES (:o,:n,:k,:t,:i,:to,:state,:lc,:le)'
        );
        foreach ($rows as $r) {
            $stmt->execute([
                ':o' => $r['owner'],
                ':n' => $r['name'],
                ':k' => $r['kind'],
                ':t' => $r['target'],
                ':i' => $r['interval'],
                ':to' => $r['timeout'],
                ':state' => 'healthy',
                ':lc' => (new \DateTimeImmutable('-2 minutes'))->format('Y-m-d H:i:s'),
                ':le' => '',
            ]);
        }
    }

    private function seedConfigurationKeys(): void
    {
        $rows = [
            ['key' => 'monitoring.poll_seconds',     'value' => '60',  'category' => 'monitoring', 'description' => 'Polling interval in seconds.'],
            ['key' => 'monitoring.retention_days',   'value' => '30',  'category' => 'monitoring', 'description' => 'Retention window for collected metrics.'],
            ['key' => 'alerting.min_severity',      'value' => 'info','category' => 'alerting',   'description' => 'Minimum severity that triggers alerts.'],
            ['key' => 'alerting.email_enabled',     'value' => '1',   'category' => 'alerting',   'description' => '1 enables email notifications (deterministic, no SMTP).'],
            ['key' => 'jobs.max_concurrent',         'value' => '4',   'category' => 'jobs',        'description' => 'Maximum concurrent scheduled jobs.'],
            ['key' => 'system.maintenance_mode',     'value' => '0',   'category' => 'system',      'description' => 'Toggle maintenance mode.'],
            ['key' => 'system.feature_flags',        'value' => 'a,b,c','category' => 'system',     'description' => 'Comma-separated feature flags.'],
        ];
        $stmt = Connection::get()->prepare(
            'INSERT INTO configuration_keys (key, value, category, description, updated_actor)
             VALUES (:k,:v,:c,:d,:a)'
        );
        foreach ($rows as $r) {
            $stmt->execute([':k' => $r['key'], ':v' => $r['value'], ':c' => $r['category'], ':d' => $r['description'], ':a' => 1]);
        }
    }

    private function seedApiTokens(): void
    {
        $raw = 'p16_seed_demo_token_do_not_use_in_prod';
        $hash = hash('sha256', $raw);
        Connection::get()->prepare(
            'INSERT INTO api_tokens (owner_id, name, token_hash, token_prefix, scopes, state)
             VALUES (:o,:n,:h,:p,:sc,:state)'
        )->execute([
            ':o' => 1,
            ':n' => 'monitoring-ro',
            ':h' => $hash,
            ':p' => 'p16_seed',
            ':sc' => 'read,health_check,metrics',
            ':state' => 'active',
        ]);
    }

    private function seedAuditEvents(): void
    {
        $pdo = Connection::get();
        $stmt = $pdo->prepare(
            'INSERT INTO audit_events (actor_id, actor_name, action, target, detail, ip_address)
             VALUES (:a,:an,:act,:t,:d,:ip)'
        );
        $samples = [
            [1, 'admin', 'account_access.login_success', 'session', ['role' => 'admin']],
            [2, 'operator', 'account_access.login_success', 'session', ['role' => 'operator']],
            [1, 'admin', 'service_control.action', 'service:web-frontend', ['action' => 'start', 'state' => 'running']],
            [2, 'operator', 'log_viewer.download', 'log_file:1', []],
            [2, 'operator', 'job_scheduler.create', 'scheduled_job', ['profile_code' => 'metric_collector']],
            [1, 'admin', 'configuration_editor.upsert', 'configuration_key:monitoring.poll_seconds', []],
        ];
        foreach ($samples as $i => $s) {
            $stmt->execute([
                ':a' => $s[0],
                ':an' => $s[1],
                ':act' => $s[2],
                ':t' => $s[3],
                ':d' => json_encode($s[4]),
                ':ip' => '127.0.0.' . ($i + 1),
            ]);
        }
    }

    private function seedScheduledJobsAndRuns(): void
    {
        $pdo = Connection::get();
        $jobRepo = new \App\Repositories\ScheduledJobRepository();
        $runRepo = new \App\Repositories\JobRunRepository();
        $profiles = $pdo->query('SELECT id, code FROM job_profiles ORDER BY id')->fetchAll();
        $byCode = [];
        foreach ($profiles as $p) {
            $byCode[$p['code']] = (int)$p['id'];
        }
        $jobs = [
            ['owner' => 2, 'profile' => 'metric_collector', 'name' => 'Collect hourly metrics', 'cron' => '0 * * * *'],
            ['owner' => 2, 'profile' => 'alert_summarize',  'name' => 'Daily alert summary',    'cron' => '0 6 * * *'],
            ['owner' => 2, 'profile' => 'db_vacuum',        'name' => 'Weekly SQLite vacuum',   'cron' => '0 3 * * 0'],
            ['owner' => 3, 'profile' => 'report_export',    'name' => 'Weekly report export',   'cron' => '30 5 * * 1'],
        ];
        foreach ($jobs as $j) {
            $id = $jobRepo->create($j['owner'], $byCode[$j['profile']], $j['name'], $j['cron']);
            $jobRepo->markRun($id, (new \DateTimeImmutable('-' . random_int(2, 36) . ' hours'))->format('Y-m-d H:i:s'));
            for ($i = 0; $i < 4; $i++) {
                $status = ['success', 'success', 'success', 'failed'][$i];
                $exit = $status === 'success' ? 0 : 1;
                $startedAt = (new \DateTimeImmutable('-' . (40 - $i * 8) . ' hours'))->format('Y-m-d H:i:s');
                $runId = $runRepo->start($id);
                $runRepo->finish(
                    $runId,
                    $status,
                    $exit,
                    'Profile ' . $j['profile'] . ' finished iteration ' . ($i + 1) . PHP_EOL,
                    $status === 'failed' ? 'Simulated failure for iteration ' . ($i + 1) . PHP_EOL : '',
                    random_int(120, 1500)
                );
                // Back-date the started/finished timestamps via direct SQL.
                $pdo->prepare('UPDATE job_runs SET started_at = :s, finished_at = :f WHERE id = :id')
                    ->execute([':s' => $startedAt, ':f' => (new \DateTimeImmutable($startedAt))->modify('+2 seconds')->format('Y-m-d H:i:s'), ':id' => $runId]);
            }
        }
    }
}