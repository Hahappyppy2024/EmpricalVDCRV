<?php
/**
 * Admin operations (HOST-12). Sections: overview | plans | accounts | settings.
 * Variables: $section, $plans, $accounts, $settings
 */
$adminNav = [
    ['overview', '/admin', 'Overview'],
    ['plans', '/admin/plans', 'Plans'],
    ['accounts', '/admin/accounts', 'Accounts'],
    ['settings', '/admin/settings', 'Settings'],
];
?>
<nav class="subnav">
    <?php foreach ($adminNav as $item): ?>
        <a class="btn btn-small <?= $section === $item[0] ? 'btn-primary' : '' ?>" href="<?= e($item[1]) ?>"><?= e($item[2]) ?></a>
    <?php endforeach; ?>
</nav>

<?php if ($section === 'overview'): ?>
    <div class="cards">
        <div class="card"><span class="card-label">Plans</span><span class="card-value"><?= count($plans ?? []) ?></span></div>
        <div class="card"><span class="card-label">Accounts</span><span class="card-value"><?= count($accounts ?? []) ?></span></div>
        <div class="card"><span class="card-label">Settings</span><span class="card-value"><?= count($settings) ?></span></div>
    </div>
    <p class="muted">Use the tabs above to manage plans, accounts, quotas and global settings.</p>
<?php elseif ($section === 'plans'): ?>
    <h2>Create plan</h2>
    <form method="post" action="/admin/plans" class="form form-narrow">
        <label>Name
            <input type="text" name="name" placeholder="Starter" required>
        </label>
        <label>Disk quota (MB)
            <input type="number" name="disk_quota" value="1024" min="1" required>
        </label>
        <label>Bandwidth quota (MB)
            <input type="number" name="bandwidth_quota" value="10240" min="1" required>
        </label>
        <label>Max domains
            <input type="number" name="max_domains" value="5" min="1" required>
        </label>
        <label>Max sites
            <input type="number" name="max_sites" value="5" min="1" required>
        </label>
        <label>Max databases
            <input type="number" name="max_databases" value="5" min="1" required>
        </label>
        <label>Monthly price
            <input type="number" name="monthly_price" value="0" step="0.01" required>
        </label>
        <button class="btn btn-primary" type="submit">Create plan</button>
    </form>

    <h2>Plans</h2>
    <table class="table">
        <thead>
        <tr><th>Name</th><th>Disk (MB)</th><th>Bandwidth (MB)</th><th>Domains</th><th>Sites</th><th>DBs</th><th>Price</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($plans as $p): ?>
            <tr>
                <td>
                    <?= e($p['name']) ?>
                    <details>
                        <summary class="muted">edit</summary>
                        <form method="post" action="/admin/plans/<?= (int) $p['id'] ?>/update" class="form form-narrow">
                            <input type="hidden" name="name" value="<?= e($p['name']) ?>">
                            <label>Disk (MB)<input type="number" name="disk_quota" value="<?= (int) $p['disk_quota'] ?>"></label>
                            <label>Bandwidth (MB)<input type="number" name="bandwidth_quota" value="<?= (int) $p['bandwidth_quota'] ?>"></label>
                            <label>Domains<input type="number" name="max_domains" value="<?= (int) $p['max_domains'] ?>"></label>
                            <label>Sites<input type="number" name="max_sites" value="<?= (int) $p['max_sites'] ?>"></label>
                            <label>DBs<input type="number" name="max_databases" value="<?= (int) $p['max_databases'] ?>"></label>
                            <label>Price<input type="number" name="monthly_price" value="<?= e((string) $p['monthly_price']) ?>" step="0.01"></label>
                            <button class="btn btn-small" type="submit">Save</button>
                        </form>
                    </details>
                </td>
                <td><?= (int) $p['disk_quota'] ?></td>
                <td><?= (int) $p['bandwidth_quota'] ?></td>
                <td><?= (int) $p['max_domains'] ?></td>
                <td><?= (int) $p['max_sites'] ?></td>
                <td><?= (int) $p['max_databases'] ?></td>
                <td>$<?= e((string) $p['monthly_price']) ?></td>
                <td>
                    <form method="post" action="/admin/plans/<?= (int) $p['id'] ?>/delete" class="inline" onsubmit="return confirm('Delete this plan?');">
                        <button class="btn btn-small btn-danger" type="submit">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php elseif ($section === 'accounts'): ?>
    <h2>Create account</h2>
    <form method="post" action="/admin/accounts" class="form form-narrow">
        <label>Username
            <input type="text" name="username" required>
        </label>
        <label>Email
            <input type="email" name="email" required>
        </label>
        <label>Full name
            <input type="text" name="full_name">
        </label>
        <label>Password (min 8 characters)
            <input type="password" name="password" required>
        </label>
        <label>Role
            <select name="role">
                <option value="customer">Customer</option>
                <option value="support">Support</option>
                <option value="admin">Admin</option>
            </select>
        </label>
        <label>Plan
            <select name="plan_id">
                <option value="">None</option>
                <?php foreach ($plans as $p): ?>
                    <option value="<?= (int) $p['id'] ?>"><?= e($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="btn btn-primary" type="submit">Create account</button>
    </form>

    <h2>Accounts</h2>
    <table class="table">
        <thead>
        <tr><th>Username</th><th>Email</th><th>Role</th><th>Plan</th><th>Domains</th><th>Sites</th><th>DBs</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($accounts as $a): ?>
            <tr>
                <td><?= e($a['username']) ?></td>
                <td><?= e($a['email']) ?></td>
                <td><span class="badge <?= badge($a['role']) ?>"><?= e($a['role']) ?></span></td>
                <td><?= e($a['plan_name'] ?? '—') ?></td>
                <td><?= (int) $a['domains_count'] ?></td>
                <td><?= (int) $a['sites_count'] ?></td>
                <td><?= (int) $a['databases_count'] ?></td>
                <td>
                    <details>
                        <summary class="muted">edit</summary>
                        <form method="post" action="/admin/accounts/<?= (int) $a['id'] ?>/update" class="form form-narrow">
                            <label>Full name<input type="text" name="full_name" value="<?= e($a['full_name']) ?>"></label>
                            <label>Role
                                <select name="role">
                                    <option value="customer" <?= $a['role'] === 'customer' ? 'selected' : '' ?>>Customer</option>
                                    <option value="support" <?= $a['role'] === 'support' ? 'selected' : '' ?>>Support</option>
                                    <option value="admin" <?= $a['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                </select>
                            </label>
                            <label>Plan
                                <select name="plan_id">
                                    <option value="">None</option>
                                    <?php foreach ($plans as $p): ?>
                                        <option value="<?= (int) $p['id'] ?>" <?= (int) $a['plan_id'] === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>New password (leave blank to keep)<input type="password" name="password"></label>
                            <button class="btn btn-small" type="submit">Save</button>
                        </form>
                    </details>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php elseif ($section === 'settings'): ?>
    <form method="post" action="/admin/settings" class="form form-narrow">
        <label>Panel name
            <input type="text" name="panel_name" value="<?= e($settings['panel_name'] ?? 'P11 Hosting Control Panel') ?>">
        </label>
        <label>Support email
            <input type="email" name="support_email" value="<?= e($settings['support_email'] ?? 'support@example.com') ?>">
        </label>
        <label>Maintenance mode
            <select name="maintenance_mode">
                <option value="off" <?= ($settings['maintenance_mode'] ?? 'off') === 'off' ? 'selected' : '' ?>>Off</option>
                <option value="on" <?= ($settings['maintenance_mode'] ?? '') === 'on' ? 'selected' : '' ?>>On</option>
            </select>
        </label>
        <label>Default plan
            <input type="text" name="default_plan" value="<?= e($settings['default_plan'] ?? 'Free') ?>">
        </label>
        <label>Backup retention (days)
            <input type="number" name="backup_retention_days" value="<?= e($settings['backup_retention_days'] ?? '30') ?>">
        </label>
        <button class="btn btn-primary" type="submit">Save settings</button>
    </form>
<?php endif; ?>
