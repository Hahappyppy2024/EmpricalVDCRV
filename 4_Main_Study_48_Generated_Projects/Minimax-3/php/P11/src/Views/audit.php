<h1>Audit logs</h1>
<section class="card">
  <form method="get" action="/audit" class="form inline">
    <label>Action <input type="text" name="action" value="<?= htmlspecialchars($filters['action'] ?? '') ?>" placeholder="e.g. domain"></label>
    <label>Target type <input type="text" name="target_type" value="<?= htmlspecialchars($filters['target_type'] ?? '') ?>"></label>
    <label>From <input type="date" name="from" value="<?= htmlspecialchars($filters['from'] ?? '') ?>"></label>
    <label>To <input type="date" name="to" value="<?= htmlspecialchars($filters['to'] ?? '') ?>"></label>
    <button class="btn" type="submit">Filter</button>
  </form>
</section>

<section class="card">
  <h2>Events</h2>
  <?php if (!$items): ?>
    <p class="muted">No matching events.</p>
  <?php else: ?>
  <table class="table">
    <thead><tr><th>ID</th><th>When</th><th>Actor</th><th>Action</th><th>Target</th><th>Details</th><th>IP</th></tr></thead>
    <tbody>
      <?php foreach ($items as $r): ?>
      <tr>
        <td><?= (int)$r['id'] ?></td>
        <td><?= htmlspecialchars($r['created_at']) ?></td>
        <td><?= htmlspecialchars(($r['username'] ?? '—') . ' (' . ($r['actor_role'] ?? '—') . ')') ?></td>
        <td><code><?= htmlspecialchars($r['action']) ?></code></td>
        <td><?= htmlspecialchars(($r['target_type'] ?? '—') . ($r['target_id'] ? '#' . $r['target_id'] : '')) ?></td>
        <td><?= htmlspecialchars($r['details'] ?? '') ?></td>
        <td><?= htmlspecialchars($r['ip'] ?? '') ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</section>