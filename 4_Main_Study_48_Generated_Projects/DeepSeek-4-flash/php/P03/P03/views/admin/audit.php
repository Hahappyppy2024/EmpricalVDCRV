<?php $pageTitle = 'Audit log'; ?>
<h1>Audit log</h1>
<div class="tabs">
  <a href="/admin">Overview</a>
  <a href="/admin/users">Users</a>
  <a href="/admin/settings">Settings</a>
  <a href="/admin/reviews">Reviews</a>
  <a href="/admin/audit">Audit log</a>
</div>

<table class="table">
  <thead><tr><th>ID</th><th>Actor</th><th>Action</th><th>Entity</th><th>Details</th><th>At</th></tr></thead>
  <tbody>
  <?php foreach ($events as $ev): ?>
    <tr>
      <td><?= (int) $ev['id'] ?></td>
      <td><?= e((string) ($ev['actor_email'] ?? 'system')) ?></td>
      <td><?= e($ev['action']) ?></td>
      <td><?= e($ev['entity_type']) ?>#<?= $ev['entity_id'] !== null ? (int) $ev['entity_id'] : '—' ?></td>
      <td><?= e($ev['details']) ?></td>
      <td><?= e($ev['created_at']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
