<h1>Domains</h1>
<section class="card">
  <form method="post" action="/domains" class="form inline">
    <label>Domain <input type="text" name="domain" placeholder="example.test" required></label>
    <label>Type
      <select name="type">
        <option value="domain">domain</option>
        <option value="subdomain">subdomain</option>
        <option value="alias">alias</option>
      </select>
    </label>
    <label>Document root <input type="text" name="document_root" placeholder="/home/user/public_html"></label>
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
    <button class="btn" type="submit">Add domain</button>
  </form>
</section>

<section class="card">
  <h2>Existing domains</h2>
  <?php if (!$items): ?>
    <p class="muted">No domains yet.</p>
  <?php else: ?>
  <table class="table">
    <thead><tr><th>ID</th><th>Domain</th><th>Type</th><th>Doc root</th><th>Status</th><th>Records</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($items as $d): ?>
      <tr>
        <td><?= (int)$d['id'] ?></td>
        <td><a href="/domains/<?= (int)$d['id'] ?>"><?= htmlspecialchars($d['domain']) ?></a></td>
        <td><?= htmlspecialchars($d['type']) ?></td>
        <td><code><?= htmlspecialchars($d['document_root']) ?></code></td>
        <td><span class="pill pill-<?= htmlspecialchars($d['status']) ?>"><?= htmlspecialchars($d['status']) ?></span></td>
        <td><?= (int)$d['record_count'] ?? 0 ?></td>
        <td>
          <form method="post" action="/domains/<?= (int)$d['id'] ?>/delete" class="inline" onsubmit="return confirm('Remove domain?')">
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