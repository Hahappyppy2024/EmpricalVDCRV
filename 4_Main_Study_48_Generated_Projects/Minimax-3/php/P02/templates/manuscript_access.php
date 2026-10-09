<h1>Manuscript Access</h1>
<p>Submission: <strong><?= htmlspecialchars($submission['title']) ?></strong> (by <?= htmlspecialchars($submission['author_name']) ?>)</p>
<?php if (empty($files)): ?>
  <p>No files attached.</p>
<?php else: ?>
  <table class="data-table">
    <thead><tr><th>File</th><th>Type</th><th>Size</th><th>Kind</th><th>Download</th></tr></thead>
    <tbody>
      <?php foreach ($files as $f): ?>
        <tr>
          <td><?= htmlspecialchars($f['original_name']) ?></td>
          <td><?= htmlspecialchars($f['mime_type']) ?></td>
          <td><?= (int)$f['size_bytes'] ?> bytes</td>
          <td><span class="badge"><?= htmlspecialchars($f['kind']) ?></span></td>
          <td>
            <a href="/manuscript_access/<?= (int)$submission['id'] ?>/<?= (int)$f['id'] ?>/download">Download</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>