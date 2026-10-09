<?php /** @var array $jobs */ /** @var array $profiles */ ?>
<header class="page-header">
    <h1>Job scheduler</h1>
    <p class="muted">SYS-05</p>
</header>

<section class="card form">
    <h3>Create new job</h3>
    <form method="post" action="/jobs">
        <label>Name
            <input type="text" name="name" required>
        </label>
        <label>Profile
            <select name="profile_id" required>
                <?php foreach ($profiles as $p): ?>
                    <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['code']) ?> — <?= htmlspecialchars($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Cron expression
            <input type="text" name="cron_expr" value="*/5 * * * *" placeholder="*/5 * * * *">
        </label>
        <button type="submit" class="btn btn-primary">Schedule</button>
    </form>
</section>

<section class="card">
    <h3>Scheduled jobs</h3>
    <table class="table">
        <thead><tr><th>Name</th><th>Profile</th><th>Cron</th><th>State</th><th>Owner</th><th>Last run</th><th>Actions</th></tr></thead>
        <tbody>
            <?php foreach ($jobs as $job): ?>
                <tr>
                    <td><a href="/job_runs/<?= (int)$job['id'] ?>"><?= htmlspecialchars($job['name']) ?></a></td>
                    <td><?= htmlspecialchars($job['profile_code']) ?> — <?= htmlspecialchars($job['profile_name']) ?></td>
                    <td><code><?= htmlspecialchars($job['cron_expr']) ?></code></td>
                    <td><span class="badge badge-<?= htmlspecialchars($job['state']) ?>"><?= htmlspecialchars($job['state']) ?></span></td>
                    <td><?= htmlspecialchars($job['owner_username']) ?></td>
                    <td><?= htmlspecialchars((string)$job['last_run_at']) ?: '—' ?></td>
                    <td>
                        <form method="post" action="/jobs/<?= (int)$job['id'] ?>/run" class="inline">
                            <button class="btn btn-xs" type="submit">Run now</button>
                        </form>
                        <?php foreach (['queued','paused','pending','deleted'] as $st): ?>
                            <?php if ($st === $job['state']) continue; ?>
                            <form method="post" action="/jobs/<?= (int)$job['id'] ?>/transition" class="inline">
                                <input type="hidden" name="state" value="<?= $st ?>">
                                <button class="btn btn-xs" type="submit">→ <?= $st ?></button>
                            </form>
                        <?php endforeach; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>