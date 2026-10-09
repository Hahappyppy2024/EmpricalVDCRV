<h1>Conference Phases</h1>
<?php if (!empty($errors)): ?>
  <div class="alert error">
    <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
  </div>
<?php endif; ?>
<?php if (!empty($success)): ?><div class="alert success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<table class="data-table">
  <thead><tr><th>Phase</th><th>Label</th><th>Start</th><th>End</th><th>Status</th><th>Edit</th></tr></thead>
  <tbody>
    <?php foreach ($phases as $p): ?>
      <tr>
        <td><?= htmlspecialchars($p['phase_key']) ?></td>
        <td><?= htmlspecialchars($p['label']) ?></td>
        <td colspan="3">
          <form method="post" action="/conference_phases/<?= (int)$p['id'] ?>" class="inline-form">
            <input type="hidden" name="_method" value="PATCH">
            <input type="date" name="start_date" value="<?= htmlspecialchars(substr($p['start_date'], 0, 10)) ?>">
            <input type="date" name="end_date" value="<?= htmlspecialchars(substr($p['end_date'], 0, 10)) ?>">
            <select name="status">
              <?php foreach (['open', 'scheduled', 'closed'] as $s): ?>
                <option value="<?= $s ?>" <?= $s === $p['status'] ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn">Save</button>
          </form>
        </td>
        <td><span class="badge"><?= htmlspecialchars($p['status']) ?></span></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<p class="muted">Updates are audited and respect the workflow timeline. The currently active phase determines which submission / review / rebuttal / decision operations succeed.</p>