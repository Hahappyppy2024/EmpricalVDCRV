<h1>Databases</h1>
<section class="card">
  <form method="post" action="/databases" class="form inline">
    <label>Database name <input type="text" name="db_name" required pattern="[a-z0-9_-]+"></label>
    <label>DB user <input type="text" name="db_user" required pattern="[a-z0-9_-]+"></label>
    <label>DB password <input type="password" name="db_pass" required minlength="6"></label>
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
    <button class="btn" type="submit">Create database</button>
  </form>
</section>

<section class="card">
  <h2>Your databases</h2>
  <?php if (!$items): ?>
    <p class="muted">No databases yet.</p>
  <?php else: ?>
  <table class="table">
    <thead><tr><th>Name</th><th>User</th><th>Size</th><th>Created</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($items as $d): ?>
      <tr>
        <td><code><?= htmlspecialchars($d['db_name']) ?></code></td>
        <td><code><?= htmlspecialchars($d['db_user']) ?></code></td>
        <td><?= (int)$d['size_mb'] ?> MB</td>
        <td><?= htmlspecialchars($d['created_at']) ?></td>
        <td class="row-actions">
          <form method="post" action="/databases/<?= (int)$d['id'] ?>/update" class="inline">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
            <input type="password" name="db_pass" minlength="6" placeholder="new pass" required>
            <button class="link" type="submit">Reset pw</button>
          </form>
          <form method="post" action="/databases/<?= (int)$d['id'] ?>/delete" class="inline" onsubmit="return confirm('Delete database?')">
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