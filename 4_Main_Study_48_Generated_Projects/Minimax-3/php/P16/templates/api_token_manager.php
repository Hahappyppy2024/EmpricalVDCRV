<?php /** @var array $tokens */ ?>
<header class="page-header">
    <h1>API token manager</h1>
    <p class="muted">SYS-11 · admin only · tokens are shown exactly once at creation.</p>
</header>

<section class="card form">
    <h3>Create monitoring token</h3>
    <form method="post" action="/api_tokens">
        <label>Name
            <input type="text" name="name" required>
        </label>
        <label>Scopes (comma-separated)
            <input type="text" name="scopes" value="read,metrics,health_check">
        </label>
        <label>Expires in (days; 0 = never)
            <input type="number" min="0" name="expires_in_days" value="30">
        </label>
        <button type="submit" class="btn btn-primary">Create token</button>
    </form>
</section>

<section class="card">
    <h3>Active tokens</h3>
    <table class="table">
        <thead><tr><th>Name</th><th>Prefix</th><th>Scopes</th><th>State</th><th>Last used</th><th>Expires</th><th>Created</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($tokens as $t): ?>
                <tr>
                    <td><?= htmlspecialchars($t['name']) ?></td>
                    <td><code><?= htmlspecialchars($t['token_prefix']) ?></code></td>
                    <td><?= htmlspecialchars($t['scopes']) ?></td>
                    <td><span class="badge badge-<?= htmlspecialchars($t['state']) ?>"><?= htmlspecialchars($t['state']) ?></span></td>
                    <td><?= htmlspecialchars((string)($t['last_used_at'] ?? '—')) ?></td>
                    <td><?= htmlspecialchars((string)($t['expires_at'] ?? 'never')) ?></td>
                    <td><?= htmlspecialchars((string)$t['created_at']) ?></td>
                    <td>
                        <?php if ($t['state'] === 'active'): ?>
                            <form method="post" action="/api_tokens/<?= (int)$t['id'] ?>/revoke" class="inline">
                                <button class="btn btn-xs btn-danger" type="submit">Revoke</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>