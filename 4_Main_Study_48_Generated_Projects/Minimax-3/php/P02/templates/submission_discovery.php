<h1>Submission Discovery</h1>
<form method="get" action="/submission_discovery" class="search-form">
  <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search title/abstract/keywords">
  <select name="status">
    <option value="">All statuses</option>
    <?php foreach (['submitted', 'under_review', 'accepted', 'rejected', 'revisions'] as $s): ?>
      <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $s)) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn">Search</button>
</form>

<?php if (empty($results)): ?>
  <p>No submissions matched your filters.</p>
<?php else: ?>
  <table class="data-table">
    <thead><tr><th>ID</th><th>Title</th><th>Author</th><th>Topic</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach ($results as $r): ?>
        <tr>
          <td>#<?= (int)$r['id'] ?></td>
          <td><?= htmlspecialchars($r['title']) ?></td>
          <td><?= htmlspecialchars($r['author_name']) ?></td>
          <td><?= htmlspecialchars($r['topic']) ?></td>
          <td><span class="badge"><?= htmlspecialchars($r['status']) ?></span></td>
          <td>
            <a href="/manuscript_access/<?= (int)$r['id'] ?>">Files</a>
            <a href="/double_blind_views/<?= (int)$r['id'] ?>">View</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>