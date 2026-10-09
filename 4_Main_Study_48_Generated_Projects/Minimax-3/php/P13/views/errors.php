<?php $pageTitle = 'Frontend API errors'; ?>
<div class="card">
  <h1>Frontend API integration &amp; errors</h1>
  <?php if (!empty($result['ok'])): ?><div class="flash ok"><?= htmlspecialchars($result['msg']) ?></div><?php endif; ?>
  <?php if (!empty($result['ok']) === false && !empty($result['msg'])): ?><div class="flash error"><?= htmlspecialchars($result['msg']) ?></div><?php endif; ?>
  <h3>Log a simulated API error</h3>
  <form method="post" action="/mail/api-errors">
    <div class="grid two">
      <label>Endpoint<input type="text" name="endpoint" value="/api/mail/message_compose" required></label>
      <label>Error code
        <select name="error_code">
          <option value="401">401 unauthorized</option>
          <option value="403">403 permission_denied</option>
          <option value="404">404 not_found</option>
          <option value="409">409 conflict</option>
          <option value="413">413 payload_too_large</option>
          <option value="415">415 unsupported_media_type</option>
          <option value="422" selected>422 invalid_input</option>
          <option value="500">500 server_error</option>
        </select>
      </label>
      <label>Error state<input type="text" name="error_state" value="invalid_input" required></label>
      <label>Message<input type="text" name="message" placeholder="what the UI should show"></label>
    </div>
    <button type="submit">Log error</button>
  </form>
  <h3>Recent API errors</h3>
  <table>
    <thead><tr><th>When</th><th>User</th><th>Endpoint</th><th>Code</th><th>State</th><th>Message</th></tr></thead>
    <tbody>
      <?php foreach ($items as $e): ?>
        <tr>
          <td><?= htmlspecialchars($e['created_at']) ?></td>
          <td><?= htmlspecialchars($e['username'] ?? '-') ?></td>
          <td><?= htmlspecialchars($e['endpoint']) ?></td>
          <td><?= (int)$e['error_code'] ?></td>
          <td><?= htmlspecialchars($e['error_state']) ?></td>
          <td><?= htmlspecialchars($e['message'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$items): ?><tr><td colspan="6" class="empty">No errors logged yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
  <h3>Recent audit trail</h3>
  <table>
    <thead><tr><th>When</th><th>Actor</th><th>Action</th><th>Target</th><th>Detail</th></tr></thead>
    <tbody>
      <?php foreach ($audit as $a): ?>
        <tr>
          <td><?= htmlspecialchars($a['created_at']) ?></td>
          <td><?= htmlspecialchars($a['actor_username'] ?? '-') ?></td>
          <td><?= htmlspecialchars($a['action']) ?></td>
          <td><?= htmlspecialchars(($a['target_type'] ?? '') . ' #' . ($a['target_id'] ?? '')) ?></td>
          <td><?= htmlspecialchars($a['detail'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$audit): ?><tr><td colspan="5" class="empty">No audit events yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>