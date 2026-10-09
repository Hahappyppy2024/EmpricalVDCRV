<?php $pageTitle = 'Attachments'; ?>
<div class="card">
  <h1>Attachments</h1>
  <?php if (!empty($error)): ?><div class="flash error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if (!empty($result['ok'])): ?><div class="flash ok">Stored &quot;<?= htmlspecialchars($result['filename']) ?>&quot; (<?= (int)$result['size'] ?> bytes).</div><?php endif; ?>
  <form method="post" action="/mail/attachments" enctype="multipart/form-data">
    <label>Upload a file (max 25 MB)
      <input type="file" name="file" required>
    </label>
    <button type="submit">Upload</button>
  </form>
  <h3>My files</h3>
  <?php if (!$items): ?>
    <p class="empty">No attachments uploaded yet.</p>
  <?php else: ?>
    <table>
      <thead><tr><th>ID</th><th>Filename</th><th>MIME</th><th>Size</th><th>Created</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($items as $a): ?>
          <tr>
            <td><?= (int)$a['id'] ?></td>
            <td><?= htmlspecialchars($a['filename']) ?></td>
            <td><?= htmlspecialchars($a['mime_type']) ?></td>
            <td><?= (int)$a['size_bytes'] ?></td>
            <td><?= htmlspecialchars($a['created_at']) ?></td>
            <td><a href="/mail/attachments/<?= (int)$a['id'] ?>/download">Download</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>