<?php
/** @var array $session */
/** @var string $host */
/** @var array|null $latest */
/** @var array $history */
/** @var array $services */
/** @var array $alerts */
/** @var array $health */
/** @var array $jobRuns */
/** @var array $backups */
/** @var array $audit */
/** @var array $hosts */

$cpu = $latest['cpu_pct'] ?? 0;
$mem = $latest['memory_pct'] ?? 0;
$disk = $latest['disk_pct'] ?? 0;
$uptime = (int)($latest['uptime_sec'] ?? 0);
$serviceState = $latest['service_state'] ?? 'healthy';
?>
<header class="page-header">
    <h1>Server dashboard</h1>
    <p class="muted">SYS-02 · host <strong><?= htmlspecialchars($host) ?></strong></p>
    <form method="get" action="/dashboard" class="inline">
        <label class="inline">Host
            <select name="host" onchange="this.form.submit()">
                <?php foreach ($hosts as $h): ?>
                    <option value="<?= htmlspecialchars($h) ?>" <?= $h === $host ? 'selected' : '' ?>><?= htmlspecialchars($h) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>
</header>

<section class="grid grid-3">
    <article class="card">
        <h3>CPU</h3>
        <p class="metric"><?= number_format((float)$cpu, 1) ?>%</p>
        <div class="bar"><span style="width:<?= min(100, $cpu) ?>%"></span></div>
    </article>
    <article class="card">
        <h3>Memory</h3>
        <p class="metric"><?= number_format((float)$mem, 1) ?>%</p>
        <div class="bar"><span style="width:<?= min(100, $mem) ?>%"></span></div>
    </article>
    <article class="card">
        <h3>Disk</h3>
        <p class="metric"><?= number_format((float)$disk, 1) ?>%</p>
        <div class="bar"><span style="width:<?= min(100, $disk) ?>%"></span></div>
    </article>
</section>

<section class="grid grid-3">
    <article class="card">
        <h3>Uptime</h3>
        <p class="metric"><?= gmdate('H:i:s', $uptime) ?></p>
        <p class="muted">Last captured: <?= htmlspecialchars((string)($latest['captured_at'] ?? 'n/a')) ?></p>
    </article>
    <article class="card">
        <h3>Service state</h3>
        <p class="badge badge-<?= htmlspecialchars($serviceState) ?>"><?= htmlspecialchars($serviceState) ?></p>
        <p class="muted">Load avg: <?= htmlspecialchars((string)($latest['load_avg'] ?? '0.0')) ?></p>
    </article>
    <article class="card">
        <h3>Snapshots stored</h3>
        <p class="metric"><?= count($history) ?></p>
        <p class="muted">last 24 shown below</p>
    </article>
</section>

<section class="card">
    <h3>History (CPU)</h3>
    <table class="table">
        <thead><tr><th>Captured</th><th>CPU %</th><th>Memory %</th><th>Disk %</th><th>Load</th><th>State</th></tr></thead>
        <tbody>
        <?php foreach ($history as $row): ?>
            <tr>
                <td><?= htmlspecialchars((string)$row['captured_at']) ?></td>
                <td><?= number_format((float)$row['cpu_pct'], 1) ?></td>
                <td><?= number_format((float)$row['memory_pct'], 1) ?></td>
                <td><?= number_format((float)$row['disk_pct'], 1) ?></td>
                <td><?= htmlspecialchars((string)$row['load_avg']) ?></td>
                <td><span class="badge badge-<?= htmlspecialchars($row['service_state']) ?>"><?= htmlspecialchars($row['service_state']) ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<section class="grid grid-2">
    <article class="card">
        <h3>Services</h3>
        <ul class="list">
            <?php foreach ($services as $svc): ?>
                <li>
                    <span class="badge badge-<?= htmlspecialchars($svc['state']) ?>"><?= htmlspecialchars($svc['state']) ?></span>
                    <?= htmlspecialchars($svc['name']) ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </article>
    <article class="card">
        <h3>Open alerts</h3>
        <ul class="list">
            <?php foreach ($alerts as $a): ?>
                <li>
                    <span class="badge badge-<?= htmlspecialchars($a['severity']) ?>"><?= htmlspecialchars($a['severity']) ?></span>
                    <?= htmlspecialchars($a['title']) ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </article>
    <article class="card">
        <h3>Recent job runs</h3>
        <ul class="list">
            <?php foreach ($jobRuns as $run): ?>
                <li>
                    <span class="badge badge-<?= htmlspecialchars($run['status']) ?>"><?= htmlspecialchars($run['status']) ?></span>
                    <?= htmlspecialchars($run['job_name']) ?> · <?= htmlspecialchars($run['profile_code']) ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </article>
    <article class="card">
        <h3>Backups</h3>
        <ul class="list">
            <?php foreach ($backups as $b): ?>
                <li><?= htmlspecialchars($b['original_name']) ?> — <?= (int)$b['size_bytes'] ?> bytes</li>
            <?php endforeach; ?>
        </ul>
    </article>
</section>

<section class="card">
    <h3>Recent audit events</h3>
    <table class="table">
        <thead><tr><th>When</th><th>Actor</th><th>Action</th><th>Target</th></tr></thead>
        <tbody>
        <?php foreach ($audit as $e): ?>
            <tr>
                <td><?= htmlspecialchars((string)$e['created_at']) ?></td>
                <td><?= htmlspecialchars((string)$e['actor_name']) ?></td>
                <td><?= htmlspecialchars((string)$e['action']) ?></td>
                <td><?= htmlspecialchars((string)$e['target']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>