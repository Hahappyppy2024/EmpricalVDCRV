<?php $pageTitle = 'Import / Export'; ?>
<div class="card">
  <h1>Import / Export</h1>
  <?php if (!empty($flash['ok'])): ?><div class="flash ok"><?= htmlspecialchars($flash['msg']) ?></div><?php endif; ?>
  <?php if (!empty($flash['ok']) === false && !empty($flash['msg'])): ?><div class="flash error"><?= htmlspecialchars($flash['msg']) ?></div><?php endif; ?>
  <?php if (!empty($_GET['imported'])): ?><div class="flash ok">Imported <?= (int)$_GET['imported'] ?> contacts.</div><?php endif; ?>
  <h3>Import contacts (CSV)</h3>
  <p>CSV header expected: <code>name,email,organization,phone,notes</code></p>
  <form method="post" action="/mail/import-export/import" enctype="multipart/form-data">
    <input type="file" name="file" accept=".csv,text/csv" required>
    <button type="submit">Upload</button>
  </form>
  <h3>Export contacts</h3>
  <p><a class="btn" href="/mail/import-export/export">Download CSV</a></p>
  <h3>Recent jobs</h3>
  <table>
    <thead><tr><th>ID</th><th>Kind</th><th>File</th><th>Rows</th><th>Status</th><th>Created</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($jobs as $j): ?>
        <tr>
          <td><?= (int)$j['id'] ?></td>
          <td><?= htmlspecialchars($j['kind']) ?></td>
          <td><?= htmlspecialchars($j['filename'] ?? '') ?></td>
          <td><?= (int)$j['row_count'] ?></td>
          <td><?= htmlspecialchars($j['status']) ?></td>
          <td><?= htmlspecialchars($j['created_at']) ?></td>
          <td>
            <?php if ($j['kind'] === 'export_contacts' && $j['storage_path']): ?>
              <a href="/mail/import-export/<?= (int)$j['id'] ?>/download">Download</a>
            <?php else: ?>
              -
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$jobs): ?><tr><td colspan="7" class="empty">No jobs yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>