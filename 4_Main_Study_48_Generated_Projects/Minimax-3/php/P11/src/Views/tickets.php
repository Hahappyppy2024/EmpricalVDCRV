<h1>Support tickets</h1>
<section class="card">
  <form method="post" action="/tickets" class="form">
    <label>Subject <input type="text" name="subject" required></label>
    <label>Body <textarea name="body" rows="4" required></textarea></label>
    <label>Priority
      <select name="priority">
        <option value="low">low</option>
        <option value="normal" selected>normal</option>
        <option value="high">high</option>
        <option value="urgent">urgent</option>
      </select>
    </label>
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
    <button class="btn" type="submit">Open ticket</button>
  </form>
</section>

<section class="card">
  <h2>Tickets</h2>
  <?php if (!$items): ?>
    <p class="muted">No tickets.</p>
  <?php else: ?>
  <table class="table">
    <thead><tr><th>ID</th><th>Subject</th><th>Customer</th><th>Priority</th><th>Status</th><th>Assignee</th><th>Updated</th></tr></thead>
    <tbody>
      <?php foreach ($items as $t): ?>
      <tr>
        <td><a href="/tickets/<?= (int)$t['id'] ?>">#<?= (int)$t['id'] ?></a></td>
        <td><?= htmlspecialchars($t['subject']) ?></td>
        <td><?= htmlspecialchars($t['customer_name'] ?? '') ?></td>
        <td><span class="pill pill-priority-<?= htmlspecialchars($t['priority']) ?>"><?= htmlspecialchars($t['priority']) ?></span></td>
        <td><span class="pill pill-<?= htmlspecialchars($t['status']) ?>"><?= htmlspecialchars($t['status']) ?></span></td>
        <td><?= htmlspecialchars($t['assignee_name'] ?? '—') ?></td>
        <td><?= htmlspecialchars($t['updated_at']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</section>