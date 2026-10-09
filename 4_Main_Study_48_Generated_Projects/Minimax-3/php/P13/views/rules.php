<?php $pageTitle = 'Filters'; ?>
<div class="card">
  <h1>Filters &amp; Rules</h1>
  <?php if (!empty($flash['ok'])): ?><div class="flash ok"><?= htmlspecialchars($flash['msg']) ?></div><?php endif; ?>
  <?php if (!empty($flash['ok']) === false && !empty($flash['msg'])): ?><div class="flash error"><?= htmlspecialchars($flash['msg']) ?></div><?php endif; ?>
  <?php if (!empty($_GET['created'])): ?><div class="flash ok">Rule created.</div><?php endif; ?>
  <?php if (!empty($_GET['updated'])): ?><div class="flash ok">Rule updated.</div><?php endif; ?>
  <?php if (!empty($_GET['deleted'])): ?><div class="flash ok">Rule deleted.</div><?php endif; ?>
  <form method="post" action="/mail/rules">
    <div class="grid two">
      <label>Name<input type="text" name="name" required></label>
      <label>Priority<input type="number" name="priority" value="100"></label>
    </div>
    <label>Conditions (e.g. <code>from_address:newsletter@example.com</code>)
      <input type="text" name="conditions" required>
    </label>
    <label>Actions (e.g. <code>move_to:Junk</code>)
      <input type="text" name="actions" required>
    </label>
    <button type="submit">Create rule</button>
  </form>
  <h3>Existing rules</h3>
  <?php if (!$items): ?>
    <p class="empty">No rules yet.</p>
  <?php else: ?>
    <table>
      <thead><tr><th>Name</th><th>Conditions</th><th>Actions</th><th>Priority</th><th>Enabled</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($items as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['name']) ?></td>
            <td><code><?= htmlspecialchars($r['conditions']) ?></code></td>
            <td><code><?= htmlspecialchars($r['actions']) ?></code></td>
            <td><?= (int)$r['priority'] ?></td>
            <td><?= $r['enabled'] ? 'yes' : 'no' ?></td>
            <td>
              <form method="post" action="/mail/rules/<?= (int)$r['id'] ?>/delete" style="display:inline">
                <button class="danger" type="submit" onclick="return confirm('Delete rule?')">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>