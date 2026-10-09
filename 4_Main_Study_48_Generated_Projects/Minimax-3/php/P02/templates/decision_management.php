<h1>Decision Management</h1>
<?php if (!empty($errors)): ?><div class="alert error"><ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if (!empty($success)): ?><div class="alert success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<table class="data-table">
  <thead><tr><th>ID</th><th>Title</th><th>Author</th><th>Status</th><th>Decision</th><th>Record / Update</th></tr></thead>
  <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td>#<?= (int)$r['id'] ?></td>
        <td><?= htmlspecialchars($r['title']) ?></td>
        <td><?= htmlspecialchars($r['author_name']) ?></td>
        <td><span class="badge"><?= htmlspecialchars($r['status']) ?></span></td>
        <td><?= $r['decision'] ? htmlspecialchars($r['decision']) : '<span class="muted">pending</span>' ?></td>
        <td>
          <form method="post" action="/decision_management/record" class="inline-form">
            <input type="hidden" name="submission_id" value="<?= (int)$r['id'] ?>">
            <select name="decision">
              <?php foreach (['accept', 'reject', 'revisions'] as $d): ?>
                <option value="<?= $d ?>" <?= $r['decision'] === $d ? 'selected' : '' ?>><?= ucfirst($d) ?></option>
              <?php endforeach; ?>
            </select>
            <input type="text" name="summary" placeholder="Summary" value="<?= htmlspecialchars($r['summary'] ?? '') ?>">
            <input type="text" name="notification" placeholder="Notification" value="<?= htmlspecialchars($r['notification'] ?? '') ?>">
            <button type="submit" class="btn"><?= $r['decision'] ? 'Update' : 'Record' ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<h2>Audit events</h2>
<table class="data-table">
  <thead><tr><th>When</th><th>Actor</th><th>Action</th><th>Target</th><th>Details</th></tr></thead>
  <tbody>
    <?php foreach ($audits as $a): ?>
      <tr>
        <td><?= htmlspecialchars($a['created_at']) ?></td>
        <td><?= htmlspecialchars($a['username'] ?? 'system') ?></td>
        <td><?= htmlspecialchars($a['action']) ?></td>
        <td><?= htmlspecialchars($a['target_type']) ?> #<?= htmlspecialchars($a['target_id']) ?></td>
        <td><?= htmlspecialchars($a['details']) ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>