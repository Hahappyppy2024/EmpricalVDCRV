<h1>SSL / certificates</h1>
<section class="card">
  <form method="post" action="/ssl" class="form">
    <label>Domain
      <select name="domain_id" required>
        <?php foreach ($myDomains as $d): ?>
          <option value="<?= (int)$d['id'] ?>"><?= htmlspecialchars($d['domain']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Common name <input type="text" name="common_name" required></label>
    <label>Issuer <input type="text" name="issuer" value="AetherPanel CA (test)"></label>
    <label>Certificate (PEM)
      <textarea name="cert_pem" rows="4" required placeholder="-----BEGIN CERTIFICATE-----"></textarea>
    </label>
    <label>Private key (PEM)
      <textarea name="key_pem" rows="4" required placeholder="-----BEGIN PRIVATE KEY-----"></textarea>
    </label>
    <label>Valid from <input type="date" name="valid_from" value="<?= date('Y-m-d') ?>"></label>
    <label>Valid to <input type="date" name="valid_to" value="<?= date('Y-m-d', strtotime('+90 days')) ?>"></label>
    <label>Status
      <select name="status">
        <option value="active">active</option>
        <option value="expired">expired</option>
        <option value="revoked">revoked</option>
      </select>
    </label>
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
    <button class="btn" type="submit">Install certificate</button>
  </form>
</section>

<section class="card">
  <h2>Your certificates</h2>
  <?php if (!$items): ?>
    <p class="muted">No certificates yet.</p>
  <?php else: ?>
  <table class="table">
    <thead><tr><th>ID</th><th>Domain</th><th>CN</th><th>Issuer</th><th>Valid until</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($items as $c): ?>
      <tr>
        <td><?= (int)$c['id'] ?></td>
        <td><?= htmlspecialchars($c['domain']) ?></td>
        <td><?= htmlspecialchars($c['common_name']) ?></td>
        <td><?= htmlspecialchars($c['issuer']) ?></td>
        <td><?= htmlspecialchars($c['valid_to']) ?></td>
        <td><span class="pill pill-<?= htmlspecialchars($c['status']) ?>"><?= htmlspecialchars($c['status']) ?></span></td>
        <td class="row-actions">
          <form method="post" action="/ssl/<?= (int)$c['id'] ?>/renew" class="inline">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
            <input type="date" name="valid_to" required>
            <button class="link" type="submit">Renew</button>
          </form>
          <form method="post" action="/ssl/<?= (int)$c['id'] ?>/revoke" class="inline" onsubmit="return confirm('Revoke?')">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
            <button class="link danger" type="submit">Revoke</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</section>