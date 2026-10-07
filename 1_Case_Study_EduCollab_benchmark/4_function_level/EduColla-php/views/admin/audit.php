<?php
// vars: $title, $logs
?>
<h1>Audit Log</h1>

<p class="muted">Note: For research purposes, some critical security events are intentionally not recorded.</p>

<table border="1" cellpadding="6" cellspacing="0" style="width:100%; border-collapse:collapse;">
  <thead>
    <tr>
      <th>Time</th>
      <th>Actor</th>
      <th>Action</th>
      <th>Target</th>
      <th>Metadata</th>
    </tr>
  </thead>
  <tbody>
    <?php if (empty($logs)): ?>
      <tr><td colspan="5"><em>No audit entries.</em></td></tr>
    <?php else: ?>
      <?php foreach ($logs as $row): ?>
        <tr>
          <td><?= h($row['created_at'] ?? '') ?></td>
          <td><?= h((string)($row['actor_user_id'] ?? '')) ?></td>
          <td><?= h($row['action'] ?? '') ?></td>
          <td><?= h($row['target'] ?? '') ?></td>
          <td><pre style="white-space:pre-wrap; margin:0;"><?= h($row['metadata'] ?? '') ?></pre></td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
  </tbody>
</table>
