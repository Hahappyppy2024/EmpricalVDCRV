<?php $pageTitle = 'Audit logs'; ?>
<div class="card">
  <h1>Admin audit logs</h1>
  <?php if (!empty($flash['ok'])): ?><div class="flash ok"><?= htmlspecialchars($flash['msg']) ?></div><?php endif; ?>
  <?php if (!empty($flash['ok']) === false && !empty($flash['msg'])): ?><div class="flash error"><?= htmlspecialchars($flash['msg']) ?></div><?php endif; ?>
  <form method="get" action="/mail/audit" class="searchbar">
    <input type="text" name="action" value="<?= htmlspecialchars($filters['action'] ?? '') ?>" placeholder="action contains">
    <select name="actor_role">
      <option value="">any role</option>
      <option value="mail_user" <?= ($filters['actor_role'] ?? '') === 'mail_user' ? 'selected' : '' ?>>mail_user</option>
      <option value="domain_admin" <?= ($filters['actor_role'] ?? '') === 'domain_admin' ? 'selected' : '' ?>>domain_admin</option>
      <option value="system_admin" <?= ($filters['actor_role'] ?? '') === 'system_admin' ? 'selected' : '' ?>>system_admin</option>
    </select>
    <select name="target_type">
      <option value="">any target</option>
      <option value="user" <?= ($filters['target_type'] ?? '') === 'user' ? 'selected' : '' ?>>user</option>
      <option value="domain" <?= ($filters['target_type'] ?? '') === 'domain' ? 'selected' : '' ?>>domain</option>
      <option value="alias" <?= ($filters['target_type'] ?? '') === 'alias' ? 'selected' : '' ?>>alias</option>
      <option value="message" <?= ($filters['target_type'] ?? '') === 'message' ? 'selected' : '' ?>>message</option>
      <option value="quarantine" <?= ($filters['target_type'] ?? '') === 'quarantine' ? 'selected' : '' ?>>quarantine</option>
    </select>
    <input type="datetime-local" name="since" value="<?= htmlspecialchars($filters['since'] ?? '') ?>">
    <button type="submit">Filter</button>
  </form>
  <table>
    <thead><tr><th>When</th><th>Actor</th><th>Role</th><th>Action</th><th>Target</th><th>Detail</th></tr></thead>
    <tbody>
      <?php foreach ($items as $a): ?>
        <tr>
          <td><?= htmlspecialchars($a['created_at']) ?></td>
          <td><?= htmlspecialchars($a['actor_username'] ?? '-') ?></td>
          <td><?= htmlspecialchars($a['actor_role'] ?? '') ?></td>
          <td><?= htmlspecialchars($a['action']) ?></td>
          <td><?= htmlspecialchars(($a['target_type'] ?? '') . ' #' . ($a['target_id'] ?? '')) ?></td>
          <td><?= htmlspecialchars($a['detail'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$items): ?><tr><td colspan="6" class="empty">No matching events.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>