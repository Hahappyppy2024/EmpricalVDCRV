<h1>Sites</h1>
<section class="card">
  <form method="post" action="/sites" class="form inline">
    <label>Site name <input type="text" name="site_name" required></label>
    <label>Document root <input type="text" name="document_root" required placeholder="/home/user/site1"></label>
    <label>PHP version
      <select name="php_version">
        <option>8.3</option><option>8.2</option><option>8.1</option><option>8.0</option><option>7.4</option>
      </select>
    </label>
    <label>Domain
      <select name="domain_id">
        <option value="">— none —</option>
        <?php foreach ($myDomains as $d): ?>
          <option value="<?= (int)$d['id'] ?>"><?= htmlspecialchars($d['domain']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
    <button class="btn" type="submit">Deploy site</button>
  </form>
</section>

<section class="card">
  <h2>Deployed sites</h2>
  <?php if (!$items): ?>
    <p class="muted">No sites deployed.</p>
  <?php else: ?>
  <table class="table">
    <thead><tr><th>ID</th><th>Name</th><th>Domain</th><th>Doc root</th><th>PHP</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($items as $s): ?>
      <tr>
        <td><?= (int)$s['id'] ?></td>
        <td><?= htmlspecialchars($s['site_name']) ?></td>
        <td><?= htmlspecialchars($s['domain'] ?? '—') ?></td>
        <td><code><?= htmlspecialchars($s['document_root']) ?></code></td>
        <td><?= htmlspecialchars($s['php_version']) ?></td>
        <td><span class="pill pill-<?= htmlspecialchars($s['status']) ?>"><?= htmlspecialchars($s['status']) ?></span></td>
        <td>
          <form method="post" action="/sites/<?= (int)$s['id'] ?>/delete" class="inline" onsubmit="return confirm('Delete site?')">
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