<h1>Domain: <?= htmlspecialchars($domain['domain']) ?></h1>
<section class="card">
  <form method="post" action="/domains/<?= (int)$domain['id'] ?>/update" class="form inline">
    <label>Status
      <select name="status">
        <?php foreach (['active','disabled','pending'] as $s): ?>
          <option value="<?= $s ?>" <?= $domain['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
    <button class="btn" type="submit">Save</button>
  </form>
</section>

<section class="card">
  <h2>DNS records</h2>
  <form method="post" action="/domains/<?= (int)$domain['id'] ?>/records" class="form inline">
    <label>Name <input type="text" name="name" value="@" required></label>
    <label>Type
      <select name="type">
        <?php foreach (['A','AAAA','CNAME','MX','TXT'] as $t): ?><option><?= $t ?></option><?php endforeach; ?>
      </select>
    </label>
    <label>Value <input type="text" name="value" required></label>
    <label>TTL <input type="number" name="ttl" value="3600" min="60"></label>
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
    <button class="btn" type="submit">Add record</button>
  </form>
  <table class="table">
    <thead><tr><th>Name</th><th>Type</th><th>Value</th><th>TTL</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($records as $r): ?>
      <tr>
        <td><?= htmlspecialchars($r['name']) ?></td>
        <td><?= htmlspecialchars($r['type']) ?></td>
        <td><code><?= htmlspecialchars($r['value']) ?></code></td>
        <td><?= (int)$r['ttl'] ?></td>
        <td>
          <form method="post" action="/domains/<?= (int)$domain['id'] ?>/records/<?= (int)$r['id'] ?>/delete" class="inline">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
            <button class="link danger" type="submit">Delete</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>