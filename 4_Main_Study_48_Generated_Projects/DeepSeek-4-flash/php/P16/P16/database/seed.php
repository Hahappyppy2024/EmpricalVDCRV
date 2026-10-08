<?php

declare(strict_types=1);

/**
 * Deterministic seed fixture generator for P16.
 *
 * Creates roles, users, sessions-free data and every workflow entity required
 * by the SYS-01..SYS-12 acceptance criteria. Safe to run repeatedly: it first
 * drops and recreates the schema via database/schema.sql.
 */

use App\Config;
use App\Database;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

if (file_exists($root . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable($root);
    $dotenv->safeLoad();
}

$config = new Config(array_merge(getenv() ?: [], $_ENV));
$db = new Database($config->get('db.path'));

$schema = file_get_contents($root . '/database/schema.sql');
if ($schema === false) {
    fwrite(STDERR, "Cannot read database/schema.sql\n");
    exit(1);
}

$db->execute($schema);

$now = time();
$stamp = function (int $offset) use ($now) {
    return date('Y-m-d H:i:s', $now + $offset);
};

$password = fn (string $p) => password_hash($p, PASSWORD_BCRYPT);

// ---------------------------------------------------------------- users (SYS-01)
$adminId = $db->insert('users', [
    'username' => 'admin', 'email' => 'admin@monitor.local',
    'password_hash' => $password('admin123'), 'full_name' => 'System Administrator',
    'role' => 'admin', 'active' => 1, 'created_at' => $stamp(-86400 * 30),
]);
$operatorId = $db->insert('users', [
    'username' => 'operator', 'email' => 'operator@monitor.local',
    'password_hash' => $password('operator123'), 'full_name' => 'NOC Operator',
    'role' => 'operator', 'active' => 1, 'created_at' => $stamp(-86400 * 30),
]);
$viewerId = $db->insert('users', [
    'username' => 'viewer', 'email' => 'viewer@monitor.local',
    'password_hash' => $password('viewer123'), 'full_name' => 'Read Only Viewer',
    'role' => 'viewer', 'active' => 1, 'created_at' => $stamp(-86400 * 20),
]);

// ------------------------------------------------------ account access (SYS-01)
$access = [
    [$operatorId, 'operator', 'login', 'success', -86400 * 29, 'Sign-in from dashboard client.'],
    [$operatorId, 'operator', 'login', 'success', -86400 * 6, 'Sign-in from SSH jump host.'],
    [null, 'unknown-user', 'login', 'failure', -86400 * 2, 'Invalid credentials or disabled account.'],
    [$operatorId, 'operator', 'logout', 'success', -3600 * 20, 'Sign-out completed; session destroyed.'],
    [$adminId, 'admin', 'login', 'success', -3600 * 5, 'Administrative sign-in.'],
    [$viewerId, 'viewer', 'login', 'success', -3600 * 1, 'Read-only sign-in.'],
];
foreach ($access as [$uid, $uname, $action, $outcome, $offset, $details]) {
    $db->insert('account_access', [
        'user_id' => $uid, 'username' => $uname, 'action' => $action, 'outcome' => $outcome,
        'ip_address' => '10.0.0.' . random_int(2, 250), 'details' => $details, 'created_at' => $stamp($offset),
    ]);
}

// --------------------------------------------------- server dashboard (SYS-02)
$metrics = [
    ['web-01', 'web01.internal', 42.1, 61.4, 73.2, 1296000, 'ok'],
    ['db-01', 'db01.internal', 78.5, 88.9, 55.0, 31104000, 'degraded'],
    ['cache-01', 'cache01.internal', 19.8, 44.3, 28.1, 8640000, 'ok'],
    ['worker-01', 'worker01.internal', 63.4, 71.0, 41.6, 6048000, 'ok'],
    ['monitoring-01', 'mon01.internal', 54.0, 66.2, 80.5, 4320000, 'degraded'],
    ['backup-01', 'bk01.internal', 12.3, 33.7, 22.9, 1728000, 'maintenance'],
];
foreach ($metrics as [$server, $hostname, $cpu, $mem, $disk, $up, $status]) {
    $db->insert('server_dashboard', [
        'server_name' => $server, 'hostname' => $hostname, 'cpu_pct' => $cpu, 'memory_pct' => $mem,
        'disk_pct' => $disk, 'uptime_seconds' => $up, 'service_status' => $status,
        'recorded_by' => $operatorId, 'created_at' => $stamp(-3600),
    ]);
}

// --------------------------------------------------------- log viewer (SYS-03)
$logs = [
    ['app.log', 'info', 'api', 'GET /api/sys/server_dashboard completed in 18ms'],
    ['app.log', 'info', 'api', 'POST /api/sys/backup_manager created backup "nightly"'],
    ['app.log', 'warning', 'scheduler', 'Job "health-check-hourly" completed with retry count 1'],
    ['app.log', 'error', 'storage', 'Disk usage above 80% on /var/lib/monitoring'],
    ['system.log', 'info', 'kernel', 'System boot completed in 4.2 seconds'],
    ['system.log', 'notice', 'network', 'Interface eth0 link up at 1000Mb/s'],
    ['system.log', 'warning', 'auth', 'Multiple failed login attempts for user unknown-user'],
    ['cron.log', 'info', 'cron', 'Ran job "log-rotate-daily" exit=0'],
    ['cron.log', 'error', 'cron', 'Job "db-replication-check" failed after 3 retries'],
    ['audit.log', 'info', 'audit', 'Config key "poll_interval" changed by admin'],
    ['audit.log', 'info', 'audit', 'API token "ci-pipeline" created by admin'],
    ['audit.log', 'warning', 'audit', 'Service "database" restarted by operator'],
];
$logFile = $root . '/storage/logs';
if (!is_dir($logFile)) {
    mkdir($logFile, 0777, true);
}
foreach ($logs as [$file, $level, $source, $message]) {
    $db->insert('log_entries', [
        'file_name' => $file, 'level' => $level, 'source' => $source,
        'message' => $message, 'created_at' => $stamp(-3600 * random_int(1, 96)),
    ]);
}
foreach (['app.log', 'system.log', 'cron.log', 'audit.log'] as $file) {
    $rows = $db->fetchAll('SELECT * FROM log_entries WHERE file_name = ? ORDER BY id ASC', [$file]);
    $lines = array_map(
        static fn (array $r) => sprintf('%s [%s] %s: %s', $r['created_at'], strtoupper($r['level']), $r['source'], $r['message']),
        $rows
    );
    file_put_contents($logFile . '/' . $file, implode(PHP_EOL, $lines) . PHP_EOL);
}

// ------------------------------------------------------ service control (SYS-04)
$services = [
    ['web-server', 'Front-end web application server', 'running', 259200, $operatorId],
    ['database', 'Primary PostgreSQL database', 'running', 31104000, $operatorId],
    ['cache', 'In-memory cache cluster', 'running', 864000, $operatorId],
    ['scheduler', 'Background job scheduler daemon', 'stopped', 0, $operatorId],
    ['backup-daemon', 'Nightly backup daemon', 'running', 604800, $adminId],
];
foreach ($services as [$name, $desc, $status, $uptime, $by]) {
    $db->insert('mock_services', [
        'name' => $name, 'description' => $desc, 'status' => $status,
        'uptime_seconds' => $uptime, 'controlled_by' => $by,
        'last_action' => 'seeded', 'last_action_at' => $stamp(-3600), 'updated_at' => $stamp(-3600),
    ]);
}

// ----------------------------------------------------- job scheduler (SYS-05)
$jobs = [
    ['nightly-backup', 'nightly_backup', 'Full configuration and metrics backup', 'daily', 'active', $operatorId],
    ['health-check-hourly', 'health_check', 'Runs health checks on all targets', 'hourly', 'active', $operatorId],
    ['log-rotate-daily', 'log_rotate', 'Rotates and archives application logs', 'daily', 'paused', $adminId],
    ['report-weekly', 'report_generation', 'Generates the weekly monitoring report', 'weekly', 'active', $operatorId],
    ['metric-aggregate', 'metric_aggregation', 'Aggregates collected metrics', 'manual', 'active', $operatorId],
];
$jobIds = [];
foreach ($jobs as [$name, $profile, $desc, $schedule, $status, $owner]) {
    $jobIds[] = $db->insert('jobs', [
        'name' => $name, 'profile' => $profile, 'description' => $desc, 'schedule' => $schedule,
        'status' => $status, 'owner_id' => $owner,
        'created_at' => $stamp(-86400 * 10), 'updated_at' => $stamp(-3600),
    ]);
}

// --------------------------------------------- job execution history (SYS-06)
$runs = [
    [$jobIds[0], 1, 'profile=nightly_backup job=nightly-backup run=1 exit=0 retries=0 duration=1800ms OK', 0, 1800, 0],
    [$jobIds[0], 2, 'profile=nightly_backup job=nightly-backup run=2 exit=0 retries=0 duration=2100ms OK', 0, 2100, 0],
    [$jobIds[1], 1, 'profile=health_check job=health-check-hourly run=1 exit=0 retries=1 duration=950ms OK', 0, 950, 1],
    [$jobIds[1], 2, 'profile=health_check job=health-check-hourly run=2 exit=1 retries=0 duration=740ms FAILED', 1, 740, 0],
    [$jobIds[2], 1, 'profile=log_rotate job=log-rotate-daily run=1 exit=0 retries=0 duration=1200ms OK', 0, 1200, 0],
    [$jobIds[3], 1, 'profile=report_generation job=report-weekly run=1 exit=0 retries=0 duration=5400ms OK', 0, 5400, 0],
    [$jobIds[3], 2, 'profile=report_generation job=report-weekly run=2 exit=0 retries=2 duration=6600ms OK', 0, 6600, 2],
];
foreach ($runs as [$job, $runNumber, $output, $exit, $duration, $retries]) {
    $db->insert('job_runs', [
        'job_id' => $job, 'run_number' => $runNumber, 'output' => $output, 'exit_status' => $exit,
        'duration_ms' => $duration, 'retry_count' => $retries,
        'started_at' => $stamp(-86400), 'finished_at' => $stamp(-86400),
    ]);
}

// ------------------------------------------------------- configuration (SYS-08)
$configs = [
    ['poll_interval', '30', 'Seconds between metric poll cycles', 'active'],
    ['retention_days', '90', 'Days to retain metrics and job history', 'active'],
    ['alert_email', 'ops@monitor.local', 'Default alert notification address', 'active'],
    ['log_level', 'info', 'Default application log level', 'active'],
    ['max_connections', '150', 'Maximum concurrent monitoring connections', 'active'],
    ['ws_port', '9090', 'Workerman WebSocket push port', 'active'],
    ['maintenance_mode', '0', 'Enable read-only maintenance mode', 'active'],
];
foreach ($configs as [$key, $value, $desc, $status]) {
    $db->insert('config_keys', [
        'config_key' => $key, 'config_value' => $value, 'description' => $desc,
        'status' => $status, 'pending_value' => null, 'updated_by' => $adminId, 'updated_at' => $stamp(-3600),
    ]);
}
$db->update('config_keys', ['pending_value' => '45', 'status' => 'pending', 'updated_by' => $adminId, 'updated_at' => $stamp(-600)], ['config_key' => 'poll_interval']);

// --------------------------------------------------------- alert center (SYS-09)
$alerts = [
    ['High CPU usage on db-01', 'warning', 'open', 'metric', null, $operatorId],
    ['Disk almost full on monitoring-01', 'warning', 'acknowledged', 'metric', $operatorId, $operatorId],
    ['Database service restart required', 'critical', 'open', 'service', null, $operatorId],
    ['Cache hit ratio dropped below 90%', 'info', 'closed', 'metric', null, $operatorId],
    ['Backup job nightly-backup succeeded', 'info', 'closed', 'job', null, $operatorId],
    ['Web UI slow response > 800ms', 'warning', 'assigned', 'metric', $operatorId, $operatorId],
];
foreach ($alerts as [$title, $severity, $status, $source, $assignee, $creator]) {
    $db->insert('alerts', [
        'title' => $title, 'severity' => $severity, 'status' => $status, 'source' => $source,
        'assignee_id' => $assignee, 'created_by' => $creator, 'comments' => '',
        'created_at' => $stamp(-3600 * random_int(1, 72)), 'updated_at' => $stamp(-600),
    ]);
}

// -------------------------------------------------- health check targets (SYS-10)
$targets = [
    ['web-ui', 'http', 'http://127.0.0.1:8787/', 8787, 30, $operatorId],
    ['api-gateway', 'http', 'http://127.0.0.1:8788/', 8788, 30, $operatorId],
    ['db-replica', 'tcp', '127.0.0.1', 3306, 60, $operatorId],
    ['cache-node', 'tcp', '127.0.0.1', 6379, 60, $operatorId],
];
foreach ($targets as [$name, $protocol, $target, $port, $interval, $by]) {
    $db->insert('health_targets', [
        'name' => $name, 'protocol' => $protocol, 'target' => $target, 'port' => $port,
        'interval_seconds' => $interval, 'status' => 'unknown', 'last_code' => null,
        'last_latency_ms' => null, 'last_checked_at' => null, 'created_by' => $by, 'created_at' => $stamp(-86400),
    ]);
}

// --------------------------------------------------- api token manager (SYS-11)
$tokens = [
    ['grafana-reader', $operatorId, null, $stamp(-86400 * 3)],
    ['ci-pipeline', $operatorId, $stamp(-3600 * 6), $stamp(-86400)],
    ['integration-test', $adminId, null, $stamp(-3600)],
];
foreach ($tokens as [$name, $owner, $revokedAt, $createdAt]) {
    $plain = 'seed-' . $name;
    $db->insert('api_tokens', [
        'name' => $name, 'token_hash' => hash('sha256', $plain),
        'token_prefix' => 'p16_seed_' . substr($name, 0, 8) . '...',
        'owner_id' => $owner, 'created_by' => $adminId,
        'revoked_at' => $revokedAt, 'last_used_at' => $stamp(-3600), 'created_at' => $createdAt,
    ]);
}

// ------------------------------------------------------- audit logs (SYS-12)
$audit = [
    [$adminId, 'admin', 'config_change', 'configuration_editor', 'Config key "poll_interval" proposed change.', -86400 * 2],
    [$adminId, 'admin', 'config_approve', 'configuration_editor', 'Config key "retention_days" change approved.', -86400 * 2],
    [$operatorId, 'operator', 'service_action', 'service_control', 'Service "scheduler" started.', -86400 * 1],
    [$operatorId, 'operator', 'job_run', 'job_scheduler', 'Job "health-check-hourly" executed.', -3600 * 8],
    [$adminId, 'admin', 'token_create', 'api_token_manager', 'API token "ci-pipeline" created.', -86400 * 3],
    [$adminId, 'admin', 'operator_create', 'audit_logs_and_admin_operations', 'Operator "viewer" created with role viewer.', -86400 * 20],
    [$operatorId, 'operator', 'backup_create', 'backup_manager', 'Backup "nightly" created.', -3600 * 12],
    [$operatorId, 'operator', 'alert_action', 'alert_center', 'Alert "Disk almost full" acknowledged.', -3600 * 5],
];
foreach ($audit as [$uid, $uname, $action, $module, $detail, $offset]) {
    $db->insert('audit_events', [
        'user_id' => $uid, 'username' => $uname, 'action' => $action, 'module' => $module,
        'detail' => $detail, 'ip_address' => '10.0.0.' . random_int(2, 250), 'created_at' => $stamp($offset),
    ]);
}

// ---------------------------------------------------- backup manager (SYS-07)
$backupDir = $config->get('backup.dir');
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0777, true);
}
$manifest = json_encode([
    'format' => 'p16-backup-v1',
    'created_at' => $stamp(-86400),
    'config' => $db->fetchAll('SELECT config_key, config_value, status FROM config_keys ORDER BY config_key'),
    'latest_metrics' => $db->fetchAll('SELECT server_name, cpu_pct, memory_pct, disk_pct, service_status FROM server_dashboard ORDER BY id DESC LIMIT 10'),
    'jobs' => $db->fetchAll('SELECT name, profile, status FROM jobs ORDER BY id'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

foreach ([
    ['weekly-snapshot', 'weekly-snapshot.backup', 'weekly-snapshot.backup', $operatorId, 'scheduled', $stamp(-86400)],
    ['pre-upgrade', 'pre-upgrade.backup', 'pre-upgrade.backup', $operatorId, 'manual', $stamp(-86400 * 4)],
] as [$name, $original, $file, $owner, $type, $createdAt]) {
    $diskPath = $backupDir . '/' . $file;
    file_put_contents($diskPath, $manifest);
    $fileId = $db->insert('stored_files', [
        'owner_id' => $owner, 'filename' => $file, 'original_name' => $original,
        'disk_path' => $diskPath, 'size_bytes' => strlen($manifest), 'mime_type' => 'application/octet-stream',
        'sha256' => hash('sha256', $manifest), 'created_at' => $createdAt,
    ]);
    $db->insert('backups', [
        'name' => $name, 'file_id' => $fileId, 'owner_id' => $owner, 'backup_type' => $type,
        'status' => 'available', 'size_bytes' => strlen($manifest), 'created_at' => $createdAt, 'restored_at' => null,
    ]);
}

// ------------------------------------------------------------------- summary
echo "Seed complete.\n";
$tables = ['users', 'sessions', 'account_access', 'server_dashboard', 'log_entries', 'mock_services',
    'jobs', 'job_runs', 'stored_files', 'backups', 'config_keys', 'alerts', 'health_targets',
    'api_tokens', 'audit_events', 'operator_actions'];
foreach ($tables as $table) {
    printf("%-22s %d rows\n", $table, (int) $db->fetchValue("SELECT COUNT(*) FROM " . $table));
}
