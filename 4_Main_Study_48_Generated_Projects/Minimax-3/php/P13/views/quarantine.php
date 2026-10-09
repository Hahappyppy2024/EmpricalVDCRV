<?php $pageTitle = 'Quarantine'; ?>
<div class="card">
  <h1>Quarantine</h1>
  <?php if (!empty($flash['ok'])): ?><div class="flash ok"><?= htmlspecialchars($flash['msg']) ?></div><?php endif; ?>
  <?php if (!empty($flash['ok']) === false && !empty($flash['msg'])): ?><div class="flash error"><?= htmlspecialchars($flash['msg']) ?></div><?php endif; ?>
  <?php if (!empty($_GET['resolved'])): ?><div class="flash ok">Item resolved.</div><?php endif; ?>
  <?php if (!empty($_GET['error'])): ?><div class="flash error">Action failed (<?= htmlspecialchars($_GET['error']) ?>).</div><?php endif; ?>
  <?php if ($domain): ?>
    <p>Reviewing domain <strong><?= htmlspecialchars($domain['name']) ?></strong></p>
    <table>
      <thead><tr><th>ID</th><th>Sender</th><th>Recipient</th><th>Subject</th><th>Reason</th><th>Status</th><th>Received</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($items as $q): ?>
          <tr>
            <td><?= (int)$q['id'] ?></td>
            <td><?= htmlspecialchars($q['sender']) ?></td>
            <td><?= htmlspecialchars($q['recipient']) ?></td>
            <td><?= htmlspecialchars($q['subject'] ?? '') ?></td>
            <td><?= htmlspecialchars($q['reason']) ?></td>
            <td><?= htmlspecialchars($q['status']) ?></td>
            <td><?= htmlspecialchars($q['received_at']) ?></td>
            <td>
              <?php if ($q['status'] === 'quarantined'): ?>
                <form method="post" action="/mail/quarantine/<?= (int)$q['id'] ?>/resolve" style="display:inline">
                  <button name="decision" value="release">Release</button>
                </form>
                <form method="post" action="/mail/quarantine/<?= (int)$q['id'] ?>/resolve" style="display:inline">
                  <button class="danger" name="decision" value="delete">Delete</button>
                </form>
              <?php else: ?>
                <em>resolved</em>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$items): ?><tr><td colspan="8" class="empty">No quarantine entries.</td></tr><?php endif; ?>
      </tbody>
    </table>
  <?php else: ?>
    <p class="empty">Select a domain from <a href="/mail/domains">domain management</a> to view its quarantine.</p>
  <?php endif; ?>
</div>