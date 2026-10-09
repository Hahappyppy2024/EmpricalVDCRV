<h1>Admin operations</h1>
<section class="card">
  <h2>Plans</h2>
  <table class="table">
    <thead><tr><th>Code</th><th>Name</th><th>Disk MB</th><th>BW MB</th><th>Domains</th><th>DBs</th><th>Price (cents)</th></tr></thead>
    <tbody>
      <?php foreach ($plans as $p): ?>
        <tr>
          <td><?= htmlspecialchars($p['code']) ?></td>
          <td><?= htmlspecialchars($p['name']) ?></td>
          <td><?= (int)$p['disk_quota_mb'] ?></td>
          <td><?= (int)$p['bandwidth_quota_mb'] ?></td>
          <td><?= (int)$p['max_domains'] ?></td>
          <td><?= (int)$p['max_databases'] ?></td>
          <td><?= (int)$p['price_cents'] ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>

<section class="card">
  <h2>Users</h2>
  <table class="table">
    <thead><tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Plan</th><th>Active</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($users as $u): ?>
      <tr>
        <td><?= (int)$u['id'] ?></td>
        <td><?= htmlspecialchars($u['username']) ?></td>
        <td><?= htmlspecialchars($u['email']) ?></td>
        <td>
          <form method="post" action="/admin/users/<?= (int)$u['id'] ?>/update" class="inline">
            <select name="role">
              <?php foreach (['customer','support','admin'] as $r): ?>
                <option <?= $u['role'] === $r ? 'selected' : '' ?>><?= $r ?></option>
              <?php endforeach; ?>
            </select>
            <select name="plan_id">
              <option value="">— none —</option>
              <?php foreach ($plans as $p): ?>
                <option value="<?= (int)$p['id'] ?>" <?= (int)$u['plan_id'] === (int)$p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['code']) ?></option>
              <?php endforeach; ?>
            </select>
            <select name="is_active">
              <option value="1" <?= $u['is_active'] ? 'selected' : '' ?>>active</option>
              <option value="0" <?= !$u['is_active'] ? 'selected' : '' ?>>inactive</option>
            </select>
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
            <button class="btn" type="submit">Save</button>
          </form>
        </td>
        <td><?= htmlspecialchars($u['plan_name'] ?? '—') ?></td>
        <td><?= $u['is_active'] ? 'yes' : 'no' ?></td>
        <td></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>

<section class="card">
  <h2>Create user</h2>
  <form method="post" action="/admin/users" class="form inline">
    <label>Username <input type="text" name="username" required></label>
    <label>Email <input type="email" name="email" required></label>
    <label>Full name <input type="text" name="full_name"></label>
    <label>Role
      <select name="role">
        <?php foreach (['customer','support','admin'] as $r): ?><option><?= $r ?></option><?php endforeach; ?>
      </select>
    </label>
    <label>Plan
      <select name="plan_id">
        <option value="">— none —</option>
        <?php foreach ($plans as $p): ?>
          <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['code']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Password <input type="text" name="password" value="Password123!"></label>
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
    <button class="btn" type="submit">Create</button>
  </form>
</section>

<section class="card">
  <h2>Global settings</h2>
  <form method="post" action="/admin/settings" class="form inline">
    <label>Key <input type="text" name="key" required></label>
    <label>Value <input type="text" name="value" required></label>
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
    <button class="btn" type="submit">Save</button>
  </form>
  <table class="table">
    <thead><tr><th>Key</th><th>Value</th><th>Updated</th></tr></thead>
    <tbody>
      <?php foreach ($settings as $k => $v): ?>
        <tr><td><?= htmlspecialchars($k) ?></td><td><?= htmlspecialchars($v) ?></td><td>—</td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>