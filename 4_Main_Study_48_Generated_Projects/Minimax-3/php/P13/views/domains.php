<?php $pageTitle = 'Domains'; ?>
<div class="card">
  <h1>Domain management</h1>
  <?php if (!empty($flash['ok'])): ?><div class="flash ok"><?= htmlspecialchars($flash['msg']) ?></div><?php endif; ?>
  <?php if (!empty($flash['ok']) === false && !empty($flash['msg'])): ?><div class="flash error"><?= htmlspecialchars($flash['msg']) ?></div><?php endif; ?>
  <ul class="folderlist">
    <?php foreach ($domains as $d): ?>
      <li><a href="/mail/domains?domain=<?= (int)$d['id'] ?>" class="<?= ($current && (int)$current['id']===(int)$d['id'])?'active':'' ?>"><?= htmlspecialchars($d['name']) ?></a></li>
    <?php endforeach; ?>
  </ul>
  <?php if ($user['role'] === 'system_admin'): ?>
    <h3>Create domain</h3>
    <form method="post" action="/mail/domains">
      <div class="grid two">
        <label>Domain name<input type="text" name="name" placeholder="example.com" required></label>
        <label>Description<input type="text" name="description"></label>
        <label>Max mailboxes<input type="number" name="max_mailboxes" value="50"></label>
        <label>Max quota MB<input type="number" name="max_quota_mb" value="5120"></label>
      </div>
      <button type="submit">Create domain</button>
    </form>
  <?php endif; ?>
</div>
<?php if ($current): ?>
<div class="card">
  <h2><?= htmlspecialchars($current['name']) ?></h2>
  <form method="post" action="/mail/domains/<?= (int)$current['id'] ?>/edit">
    <div class="grid two">
      <label>Description<input type="text" name="description" value="<?= htmlspecialchars($current['description'] ?? '') ?>"></label>
      <label>Status
        <select name="status">
          <option value="active" <?= $current['status']==='active'?'selected':'' ?>>active</option>
          <option value="disabled" <?= $current['status']==='disabled'?'selected':'' ?>>disabled</option>
        </select>
      </label>
      <label>Max mailboxes<input type="number" name="max_mailboxes" value="<?= (int)$current['max_mailboxes'] ?>"></label>
      <label>Max quota MB<input type="number" name="max_quota_mb" value="<?= (int)$current['max_quota_mb'] ?>"></label>
    </div>
    <button type="submit">Save</button>
  </form>
  <h3>Aliases</h3>
  <ul>
    <?php foreach ($aliases as $a): ?>
      <li><?= htmlspecialchars($a['source']) ?> &rarr; <?= htmlspecialchars($a['destination']) ?> <?= $a['enabled'] ? '' : '(disabled)' ?></li>
    <?php endforeach; ?>
    <?php if (!$aliases): ?><li class="empty">No aliases yet.</li><?php endif; ?>
  </ul>
  <form method="post" action="/mail/domains/<?= (int)$current['id'] ?>/aliases">
    <div class="grid two">
      <label>Source<input type="text" name="source" placeholder="info@example.com" required></label>
      <label>Destination<input type="email" name="destination" placeholder="user@example.com" required></label>
    </div>
    <button type="submit">Add alias</button>
  </form>
  <h3>Mailboxes</h3>
  <table>
    <thead><tr><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Quota</th></tr></thead>
    <tbody>
      <?php foreach ($members as $m): ?>
        <tr>
          <td><?= htmlspecialchars($m['username']) ?></td>
          <td><?= htmlspecialchars($m['email']) ?></td>
          <td><?= htmlspecialchars($m['role']) ?></td>
          <td><?= htmlspecialchars($m['status']) ?></td>
          <td><?= (int)$m['mailbox_used_mb'] ?> / <?= (int)$m['mailbox_quota_mb'] ?> MB</td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$members): ?><tr><td colspan="5" class="empty">No mailboxes.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>