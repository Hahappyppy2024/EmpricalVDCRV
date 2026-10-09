<h1>Backups</h1>
<section class="card">
  <h2>Create backup</h2>
  <form method="post" action="/backups" class="form inline">
    <label>Scope
      <select name="scope">
        <option value="site">site</option>
        <option value="database">database</option>
        <option value="full">full</option>
      </select>
    </label>
    <label>Target ID <input type="number" name="target_id" min="1" placeholder="optional"></label>
    <label>Note <input type="text" name="note" placeholder="optional"></label>
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
    <button class="btn" type="submit">Create</button>
  </form>
</section>

<section class="card">
  <h2>Upload backup</h2>
  <form method="post" action="/backups/upload" enctype="multipart/form-data" class="form inline">
    <label>File <input type="file" name="file" required></label>
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
    <button class="btn" type="submit">Upload</button>
  </form>
</section>

<section class="card">
  <h2>Existing backups</h2>
  <?php if (!$items): ?>
    <p class="muted">No backups yet.</p>
  <?php else: ?>
  <table class="table">
    <thead><tr><th>ID</th><th>Filename</th><th>Scope</th><th>Target</th><th>Size</th><th>Status</th><th>Created</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($items as $b): ?>
      <tr>
        <td><?= (int)$b['id'] ?></td>
        <td><?= htmlspecialchars($b['filename']) ?></td>
        <td><?= htmlspecialchars($b['scope']) ?></td>
        <td><?= (int)($b['target_id'] ?? 0) ?: '—' ?></td>
        <td><?= (int)$b['size_bytes'] ?> B</td>
        <td><span class="pill pill-<?= htmlspecialchars($b['status']) ?>"><?= htmlspecialchars($b['status']) ?></span></td>
        <td><?= htmlspecialchars($b['created_at']) ?></td>
        <td class="row-actions">
          <form method="post" action="/backups/<?= (int)$b['id'] ?>/restore" class="inline" onsubmit="return confirm('Restore this backup?')">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
            <button class="link" type="submit">Restore</button>
          </form>
          <form method="post" action="/backups/<?= (int)$b['id'] ?>/delete" class="inline" onsubmit="return confirm('Delete?')">
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