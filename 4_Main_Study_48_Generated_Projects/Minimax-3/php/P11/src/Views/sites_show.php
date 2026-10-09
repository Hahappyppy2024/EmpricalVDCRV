<h1>Site: <?= htmlspecialchars($site['site_name']) ?></h1>
<section class="card">
  <ul class="kv">
    <li><span>ID</span><b><?= (int)$site['id'] ?></b></li>
    <li><span>Status</span><b><span class="pill pill-<?= htmlspecialchars($site['status']) ?>"><?= htmlspecialchars($site['status']) ?></span></b></li>
    <li><span>Document root</span><b><code><?= htmlspecialchars($site['document_root']) ?></code></b></li>
    <li><span>PHP version</span><b><?= htmlspecialchars($site['php_version']) ?></b></li>
    <li><span>Created</span><b><?= htmlspecialchars($site['created_at']) ?></b></li>
  </ul>
  <a class="btn" href="/files?site_id=<?= (int)$site['id'] ?>">Browse files</a>
</section>

<section class="card">
  <h2>Update site</h2>
  <form method="post" action="/sites/<?= (int)$site['id'] ?>/update" class="form inline">
    <label>Name <input type="text" name="site_name" value="<?= htmlspecialchars($site['site_name']) ?>" required></label>
    <label>Doc root <input type="text" name="document_root" value="<?= htmlspecialchars($site['document_root']) ?>" required></label>
    <label>PHP
      <select name="php_version">
        <?php foreach (['8.3','8.2','8.1','8.0','7.4'] as $v): ?>
          <option <?= $site['php_version'] === $v ? 'selected' : '' ?>><?= $v ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Status
      <select name="status">
        <?php foreach (['deployed','pending','failed'] as $st): ?>
          <option <?= $site['status'] === $st ? 'selected' : '' ?>><?= $st ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
    <button class="btn" type="submit">Save</button>
  </form>
</section>