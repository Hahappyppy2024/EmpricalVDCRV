<?php
/**
 * Resource usage (HOST-09). Variables: $dashboard, $overview
 */
$latest = $dashboard['latest'] ?? [];
?>
<div class="cards">
    <div class="card">
        <span class="card-label">CPU usage</span>
        <span class="card-value"><?= e((string) ($latest['cpu_usage'] ?? 0)) ?>%</span>
    </div>
    <div class="card">
        <span class="card-label">Disk used</span>
        <span class="card-value"><?= e((string) ($latest['disk_used'] ?? 0)) ?> MB</span>
    </div>
    <div class="card">
        <span class="card-label">Traffic used</span>
        <span class="card-value"><?= e((string) ($latest['traffic_used'] ?? 0)) ?> MB</span>
    </div>
    <div class="card">
        <span class="card-label">Quota usage</span>
        <span class="card-value"><?= e((string) ($latest['quota_percent'] ?? 0)) ?>%</span>
    </div>
</div>

<h2>14-day history</h2>
<table class="table">
    <thead>
    <tr><th>Recorded</th><th>CPU %</th><th>Disk (MB)</th><th>Traffic (MB)</th><th>Quota %</th></tr>
    </thead>
    <tbody>
    <?php foreach (array_slice($dashboard['history'], -14) as $row): ?>
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

<?php if ($overview !== []): ?>
    <h2>Customer overview (support)</h2>
    <table class="table">
        <thead>
        <tr><th>Customer</th><th>Plan</th><th>CPU %</th><th>Disk (MB)</th><th>Traffic (MB)</th><th>Quota %</th></tr>
        </thead>
        <tbody>
        <?php foreach ($overview as $c): ?>
            <tr>
                <td><?= e($c['username']) ?> <span class="muted"><?= e($c['email']) ?></span></td>
                <td><?= e($c['plan_name']) ?></td>
                <td><?= e((string) $c['cpu_usage']) ?></td>
                <td><?= e((string) $c['disk_used']) ?></td>
                <td><?= e((string) $c['traffic_used']) ?></td>
                <td><?= e((string) $c['quota_percent']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
