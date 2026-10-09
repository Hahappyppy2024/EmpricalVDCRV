<h1>File manager</h1>
<section class="card">
  <form method="post" action="/files" enctype="multipart/form-data" class="form inline">
    <label>Site
      <select name="site_id">
        <option value="">— all —</option>
        <?php foreach ($sites as $s): ?>
          <option value="<?= (int)$s['id'] ?>" <?= $currentSite === (int)$s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['site_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Parent path <input type="text" name="parent_path" value="/"></label>
    <label>File <input type="file" name="file" required></label>
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
    <button class="btn" type="submit">Upload</button>
  </form>
  <p class="muted">Maximum file size: 25 MB. Stored under <code>storage/uploads/{user}/</code>.</p>
</section>

<section class="card">
  <h2>Files</h2>
  <?php if (!$items): ?>
    <p class="muted">No files yet.</p>
  <?php else: ?>
  <table class="table">
    <thead><tr><th>Path</th><th>Size</th><th>Type</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($items as $f): ?>
      <tr>
        <td><code><?= htmlspecialchars($f['path']) ?></code></td>
        <td><?= (int)$f['size_bytes'] ?> B</td>
        <td><?= htmlspecialchars($f['mime_type'] ?? '—') ?></td>
        <td class="row-actions">
          <form method="post" action="/files/<?= (int)$f['id'] ?>/rename" class="inline">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
            <input type="text" name="new_name" placeholder="new name" required>
            <button class="link" type="submit">Rename</button>
          </form>
          <form method="post" action="/files/<?= (int)$f['id'] ?>/delete" class="inline" onsubmit="return confirm('Delete file?')">
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