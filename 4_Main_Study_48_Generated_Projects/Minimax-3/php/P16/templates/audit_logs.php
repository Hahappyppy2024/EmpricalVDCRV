<?php /** @var array $events */ /** @var array $users */ ?>
<header class="page-header">
    <h1>Audit logs and admin operations</h1>
    <p class="muted">SYS-12 · admin only</p>
</header>

<form method="get" action="/audit" class="card form inline-form">
    <label>Action
        <input type="text" name="action" placeholder="e.g. service_control.action">
    </label>
    <label>Actor
        <input type="text" name="actor" placeholder="username">
    </label>
    <button type="submit" class="btn">Filter</button>
</form>

<section class="card">
    <h3>Recent events</h3>
    <table class="table">
        <thead><tr><th>When</th><th>Actor</th><th>Action</th><th>Target</th><th>Detail</th></tr></thead>
        <tbody>
            <?php foreach ($events as $e): ?>
                <tr>
                    <td><?= htmlspecialchars((string)$e['created_at']) ?></td>
                    <td><?= htmlspecialchars((string)$e['actor_name']) ?></td>
                    <td><code><?= htmlspecialchars((string)$e['action']) ?></code></td>
                    <td><?= htmlspecialchars((string)$e['target']) ?></td>
                    <td><pre class="pre"><?= htmlspecialchars((string)$e['detail']) ?></pre></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>

<section class="card">
    <h3>User management</h3>
    <table class="table">
        <thead><tr><th>Username</th><th>Full name</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= htmlspecialchars($user['username']) ?></td>
                    <td><?= htmlspecialchars($user['full_name']) ?></td>
                    <td><?= htmlspecialchars($user['email']) ?></td>
                    <td><?= htmlspecialchars($user['role']) ?></td>
                    <td><?= (int)$user['enabled'] === 1 ? 'enabled' : 'disabled' ?></td>
                    <td>
                        <?php if ((int)$user['enabled'] === 1): ?>
                            <form method="post" action="/audit/users" class="inline">
                                <input type="hidden" name="action" value="disable">
                                <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                <button class="btn btn-xs" type="submit">Disable</button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="/audit/users" class="inline">
                                <input type="hidden" name="action" value="enable">
                                <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                <button class="btn btn-xs" type="submit">Enable</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($user['role'] === 'operator'): ?>
                            <form method="post" action="/audit/users" class="inline">
                                <input type="hidden" name="action" value="promote">
                                <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                <button class="btn btn-xs" type="submit">Promote</button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="/audit/users" class="inline">
                                <input type="hidden" name="action" value="demote">
                                <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                <button class="btn btn-xs" type="submit">Demote</button>
                            </form>
                        <?php endif; ?>
                        <form method="post" action="/audit/users" class="inline">
                            <input type="hidden" name="action" value="reset_password">
                            <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                            <button class="btn btn-xs" type="submit">Reset password</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>