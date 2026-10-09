<h1>Scheduled tasks</h1>
<section class="card">
  <form method="post" action="/cron" class="form inline">
    <label>Name <input type="text" name="name" required></label>
    <label>Schedule <input type="text" name="schedule" value="0 * * * *" placeholder="cron expression" required></label>
    <label>Command <input type="text" name="command" required></label>
    <label>Status
      <select name="status">
        <option value="active">active</option>
        <option value="disabled">disabled</option>
      </select>
    </label>
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
    <button class="btn" type="submit">Schedule job</button>
  </form>
</section>

<section class="card">
  <h2>Your cron jobs</h2>
  <?php if (!$items): ?>
    <p class="muted">No jobs scheduled.</p>
  <?php else: ?>
  <table class="table">
    <thead><tr><th>Name</th><th>Schedule</th><th>Command</th><th>Last run</th><th>Status</th><th>Active</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($items as $j): ?>
      <tr>
        <td><?= htmlspecialchars($j['name']) ?></td>
        <td><code><?= htmlspecialchars($j['schedule']) ?></code></td>
        <td><code><?= htmlspecialchars($j['command']) ?></code></td>
        <td><?= htmlspecialchars($j['last_run'] ?? '—') ?> (<?= htmlspecialchars($j['last_status'] ?? '—') ?>)</td>
        <td><?= (int)$j['is_active'] ? 'active' : 'disabled' ?></td>
        <td>
          <form method="post" action="/cron/<?= (int)$j['id'] ?>/run" class="inline">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
            <button class="link" type="submit">Run now</button>
          </form>
          <form method="post" action="/cron/<?= (int)$j['id'] ?>/delete" class="inline" onsubmit="return confirm('Delete?')">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
            <button class="link danger" type="submit">Delete</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</section>