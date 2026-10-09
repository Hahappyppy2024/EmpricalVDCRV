<?php /** @var array $job */ /** @var array $runs */ ?>
<header class="page-header">
    <h1><?= htmlspecialchars($job['name']) ?></h1>
    <p class="muted">SYS-06 · job #<?= (int)$job['id'] ?> · profile <?= htmlspecialchars($job['profile_code']) ?> · state <?= htmlspecialchars($job['state']) ?></p>
</header>

<section class="card">
    <h3>Cron</h3>
    <p><code><?= htmlspecialchars($job['cron_expr']) ?></code></p>
    <h3>Recent runs</h3>
    <table class="table">
        <thead><tr><th>Run #</th><th>Status</th><th>Exit</th><th>Duration</th><th>Started</th><th>Finished</th></tr></thead>
        <tbody>
            <?php foreach ($runs as $run): ?>
                <tr>
                    <td><a href="/job_runs/run/<?= (int)$run['id'] ?>">#<?= (int)$run['id'] ?></a></td>
                    <td><span class="badge badge-<?= htmlspecialchars($run['status']) ?>"><?= htmlspecialchars($run['status']) ?></span></td>
                    <td><?= (int)$run['exit_code'] ?></td>
                    <td><?= (int)$run['duration_ms'] ?> ms</td>
                    <td><?= htmlspecialchars((string)$run['started_at']) ?></td>
                    <td><?= htmlspecialchars((string)$run['finished_at']) ?: '—' ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>