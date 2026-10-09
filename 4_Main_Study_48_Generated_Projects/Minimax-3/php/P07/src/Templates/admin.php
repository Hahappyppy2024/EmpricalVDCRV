<section class="card">
    <h2>Admin console</h2>
    <h3>Users</h3>
    <table>
        <thead><tr><th>Email</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td><?= htmlspecialchars($u['role']) ?></td>
                <td><?= htmlspecialchars($u['status']) ?></td>
                <td>
                    <form method="post" class="api-form" data-method="patch" action="/api/file/admin_console/<?= (int)$u['id'] ?>">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                        <input type="hidden" name="action" value="update_user">
                        <select name="status">
                            <option value="active" <?= $u['status']==='active'?'selected':'' ?>>active</option>
                            <option value="suspended" <?= $u['status']==='suspended'?'selected':'' ?>>suspended</option>
                        </select>
                        <select name="role">
                            <option value="user" <?= $u['role']==='user'?'selected':'' ?>>user</option>
                            <option value="admin" <?= $u['role']==='admin'?'selected':'' ?>>admin</option>
                            <option value="recipient" <?= $u['role']==='recipient'?'selected':'' ?>>recipient</option>
                        </select>
                        <button type="submit">Save</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <h3>Settings</h3>
    <form method="post" action="/api/file/admin_console" class="api-form" data-method="post">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="update_settings">
        <label>Default user quota (bytes) <input type="number" name="default_user_quota" value="<?= (int)$settings['default_user_quota'] ?>"></label>
        <label>Default team quota (bytes) <input type="number" name="default_team_quota" value="<?= (int)$settings['default_team_quota'] ?>"></label>
        <label>Retention days <input type="number" name="retention_days" value="<?= (int)$settings['retention_days'] ?>"></label>
        <label>Blocked file types <input type="text" name="blocked_file_types" value="<?= htmlspecialchars($settings['blocked_file_types']) ?>"></label>
        <button type="submit">Save settings</button>
    </form>
</section>