<?php /** @var array $targets */ ?>
<header class="page-header">
    <h1>Health check targets</h1>
    <p class="muted">SYS-10 · HTTP, TCP, or script-based checks.</p>
</header>

<section class="card form">
    <h3>New target</h3>
    <form method="post" action="/health_targets">
        <div class="grid grid-2">
            <label>Name
                <input type="text" name="name" required>
            </label>
            <label>Kind
                <select name="kind">
                    <?php foreach (['http','tcp','script'] as $k): ?>
                        <option value="<?= $k ?>"><?= $k ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <label>Target URL / host:port / script path
            <input type="text" name="target" required>
        </label>
        <div class="grid grid-2">
            <label>Interval (seconds)
                <input type="number" min="10" name="interval_sec" value="60">
            </label>
            <label>Timeout (milliseconds)
                <input type="number" min="100" name="timeout_ms" value="2000">
            </label>
        </div>
        <button type="submit" class="btn btn-primary">Save</button>
    </form>
</section>

<section class="card">
    <h3>Configured targets</h3>
    <table class="table">
        <thead><tr><th>Name</th><th>Kind</th><th>Target</th><th>Interval</th><th>Timeout</th><th>State</th><th>Last check</th><th>Owner</th><th>Actions</th></tr></thead>
        <tbody>
            <?php foreach ($targets as $t): ?>
                <tr>
                    <td><?= htmlspecialchars($t['name']) ?></td>
                    <td><?= htmlspecialchars($t['kind']) ?></td>
                    <td><code><?= htmlspecialchars($t['target']) ?></code></td>
                    <td><?= (int)$t['interval_sec'] ?> s</td>
                    <td><?= (int)$t['timeout_ms'] ?> ms</td>
                    <td><span class="badge badge-<?= htmlspecialchars($t['state']) ?>"><?= htmlspecialchars($t['state']) ?></span></td>
                    <td><?= htmlspecialchars((string)($t['last_check_at'] ?? '—')) ?></td>
                    <td><?= htmlspecialchars($t['owner_username']) ?></td>
                    <td>
                        <form method="post" action="/health_targets/<?= (int)$t['id'] ?>/check" class="inline">
                            <button class="btn btn-xs" type="submit">Run check</button>
                        </form>
                        <form method="post" action="/health_targets/<?= (int)$t['id'] ?>/delete" class="inline">
                            <button class="btn btn-xs btn-danger" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>