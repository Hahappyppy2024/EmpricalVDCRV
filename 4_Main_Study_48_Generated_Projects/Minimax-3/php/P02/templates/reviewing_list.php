<h1>Reviewing</h1>
<?php if (empty($assignments)): ?>
  <p>No assignments.</p>
<?php else: ?>
  <table class="data-table">
    <thead><tr><th>Submission</th><th>Due</th><th>Status</th><th>Action</th></tr></thead>
    <tbody>
      <?php foreach ($assignments as $a): ?>
        <tr>
          <td><?= htmlspecialchars($a['submission_title']) ?></td>
          <td><?= htmlspecialchars(substr($a['due_date'], 0, 10)) ?></td>
          <td><span class="badge"><?= htmlspecialchars($a['status']) ?></span> <?= $a['review_id'] ? '<span class="muted">(submitted)</span>' : '' ?></td>
          <td>
            <a class="btn" href="/reviewing/<?= (int)$a['id'] ?>">Open</a>
            <a href="/double_blind_views/<?= (int)$a['submission_id'] ?>">View paper</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>