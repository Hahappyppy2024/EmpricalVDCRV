<?php /** @var array $runs */ ?>
<header class="page-header">
    <h1>Job execution history</h1>
    <p class="muted">SYS-06 · all jobs, newest first</p>
</header>

<section class="card">
    <table class="table">
        <thead><tr><th>Run #</th><th>Job</th><th>Profile</th><th>Status</th><th>Exit</th><th>Duration</th><th>Started</th><th>Finished</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($runs as $run): ?>
                <tr>
                    <td>#<?= (int)$run['id'] ?></td>
                    <td><?= htmlspecialchars($run['job_name']) ?></td>
                    <td><?= htmlspecialchars($run['profile_code']) ?></td>
                    <td><span class="badge badge-<?= htmlspecialchars($run['status']) ?>"><?= htmlspecialchars($run['status']) ?></span></td>
                    <td><?= (int)$run['exit_code'] ?></td>
                    <td><?= (int)$run['duration_ms'] ?> ms</td>
                    <td><?= htmlspecialchars((string)$run['started_at']) ?></td>
                    <td><?= htmlspecialchars((string)$run['finished_at']) ?: '—' ?></td>
                    <td><a class="btn btn-xs" href="/job_runs/run/<?= (int)$run['id'] ?>">View</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>