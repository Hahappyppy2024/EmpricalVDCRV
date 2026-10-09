<?php /** @var array $services */ /** @var array $session */ ?>
<header class="page-header">
    <h1>Service control</h1>
    <p class="muted">SYS-04 · admins can start/stop/restart; operators can inspect.</p>
</header>

<section class="card">
    <table class="table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Description</th>
                <th>State</th>
                <th>PID</th>
                <th>Last action</th>
                <th>Started</th>
                <th>Controls</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($services as $svc): ?>
                <tr>
                    <td><?= htmlspecialchars($svc['name']) ?></td>
                    <td><?= htmlspecialchars($svc['description']) ?></td>
                    <td><span class="badge badge-<?= htmlspecialchars($svc['state']) ?>"><?= htmlspecialchars($svc['state']) ?></span></td>
                    <td><?= (int)$svc['pid'] ?></td>
                    <td><?= htmlspecialchars($svc['last_action']) ?: '—' ?></td>
                    <td><?= htmlspecialchars((string)$svc['started_at']) ?: '—' ?></td>
                    <td>
                        <?php if ($session['role'] === 'admin'): ?>
                            <?php foreach (['start','stop','restart'] as $a): ?>
                                <form method="post" action="/services/<?= (int)$svc['id'] ?>/act" class="inline">
                                    <input type="hidden" name="action" value="<?= $a ?>">
                                    <button class="btn btn-xs" type="submit"><?= $a ?></button>
                                </form>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="muted">admin only</span>
                        <?php endif; ?>
                        <form method="post" action="/services/<?= (int)$svc['id'] ?>/act" class="inline">
                            <input type="hidden" name="action" value="inspect">
                            <button class="btn btn-xs" type="submit">inspect</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>