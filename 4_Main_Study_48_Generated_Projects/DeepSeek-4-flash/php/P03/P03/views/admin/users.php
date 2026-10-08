<?php $pageTitle = 'Manage users'; ?>
<h1>Manage users</h1>
<div class="tabs">
  <a href="/admin">Overview</a>
  <a href="/admin/users">Users</a>
  <a href="/admin/settings">Settings</a>
  <a href="/admin/reviews">Reviews</a>
  <a href="/admin/audit">Audit log</a>
</div>

<table class="table">
  <thead>
  <tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Active</th><th>Manage</th></tr>
  </thead>
  <tbody>
  <?php foreach ($users as $u): ?>
    <tr>
      <td><?= (int) $u['id'] ?></td>
      <td><?= e($u['name']) ?></td>
      <td><?= e($u['email']) ?></td>
      <td><?= e($u['role']) ?></td>
      <td><?= (int) $u['active'] === 1 ? 'yes' : '<span class="badge badge-warn">disabled</span>' ?></td>
      <td>
        <?php if ((int) $u['id'] !== (int) ($user['id'] ?? 0)): ?>
          <form method="post" action="/admin/users/<?= (int) $u['id'] ?>/manage" class="inline-form">
            <?= csrf_field($csrf) ?>
            <select name="role">
              <?php foreach (['customer', 'seller', 'moderator', 'admin'] as $r): ?>
                <option value="<?= e($r) ?>" <?= $u['role'] === $r ? 'selected' : '' ?>><?= e($r) ?></option>
              <?php endforeach; ?>
            </select>
            <select name="active">
              <option value="1" <?= (int) $u['active'] === 1 ? 'selected' : '' ?>>active</option>
              <option value="0" <?= (int) $u['active'] === 0 ? 'selected' : '' ?>>disabled</option>
            </select>
            <button class="btn btn-sm" type="submit">Save</button>
          </form>
        <?php else: ?>
          <span class="muted small">you</span>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
