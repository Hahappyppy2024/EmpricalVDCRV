<?php
/** Dashboard. Variables: $usage, $limits, $usageData */
$usage = $usageData['usage'] ?? [];
$limits = $usageData['limits'] ?? [];
$history = $usageData['history'] ?? [];
?>
<div class="cards">
    <div class="card">
        <span class="card-label">Domains</span>
        <span class="card-value"><?= (int) ($usage['domains'] ?? 0) ?> / <?= (int) ($limits['max_domains'] ?? 5) ?></span>
    </div>
    <div class="card">
        <span class="card-label">Sites</span>
        <span class="card-value"><?= (int) ($usage['sites'] ?? 0) ?> / <?= (int) ($limits['max_sites'] ?? 5) ?></span>
    </div>
    <div class="card">
        <span class="card-label">Databases</span>
        <span class="card-value"><?= (int) ($usage['databases'] ?? 0) ?> / <?= (int) ($limits['max_databases'] ?? 5) ?></span>
    </div>
    <div class="card">
        <span class="card-label">Disk used</span>
        <span class="card-value"><?= e(formatBytes((int) (($usageData['disk_used_mb'] ?? 0) * 1024 * 1024))) ?> / <?= e(formatBytes((int) ($usageData['disk_quota_mb'] ?? 0) * 1024 * 1024)) ?></span>
    </div>
</div>

<div class="cards">
    <div class="card">
        <span class="card-label">Backups</span>
        <span class="card-value"><?= (int) ($usage['backups'] ?? 0) ?></span>
    </div>
    <div class="card">
        <span class="card-label">Certificates</span>
        <span class="card-value"><?= (int) ($usage['certificates'] ?? 0) ?></span>
    </div>
    <div class="card">
        <span class="card-label">Scheduled tasks</span>
        <span class="card-value"><?= (int) ($usage['tasks'] ?? 0) ?></span>
    </div>
</div>

<h2>Resource usage history</h2>
<table class="table">
    <thead>
    <tr><th>Recorded</th><th>CPU %</th><th>Disk (MB)</th><th>Traffic (MB)</th><th>Quota %</th></tr>
    </thead>
    <tbody>
    <?php foreach ($history as $row): ?>
        <tr>
            <td><?= e($row['recorded_at']) ?></td>
            <td><?= e((string) $row['cpu_usage']) ?></td>
            <td><?= e((string) $row['disk_used']) ?></td>
            <td><?= e((string) $row['traffic_used']) ?></td>
            <td><?= e((string) $row['quota_percent']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
