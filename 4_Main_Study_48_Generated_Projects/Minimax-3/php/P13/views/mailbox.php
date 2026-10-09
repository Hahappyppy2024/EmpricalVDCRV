<?php $pageTitle = 'Mailbox'; $user = $user ?? []; ?>
<div class="card">
  <h1>Mailbox</h1>
  <form method="get" action="/mail" class="searchbar">
    <input type="text" name="q" value="<?= htmlspecialchars($search ?? '') ?>" placeholder="Search subject, body, sender">
    <button type="submit">Search</button>
  </form>
</div>
<div class="layout">
  <aside class="sidebar">
    <h3>Folders</h3>
    <ul class="folderlist">
      <?php foreach ($folders as $f): ?>
        <li>
          <a href="/mail?folder=<?= (int)$f['id'] ?>" class="<?= ($currentFolder && (int)$currentFolder['id'] === (int)$f['id']) ? 'active' : '' ?>">
            <?= htmlspecialchars($f['name']) ?>
            <span class="count">(<?= (int)$f['total_count'] ?><?= $f['unread_count'] > 0 ? ' / ' . (int)$f['unread_count'] . ' unread' : '' ?>)</span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
    <form method="post" action="/api/mail/mailbox_overview" class="api-call-form" data-method="POST">
      <h4>API: create folder</h4>
      <input type="text" name="name" placeholder="Folder name" required>
      <input type="text" name="folder_type" placeholder="folder_type" value="custom">
      <button type="submit">Create</button>
      <pre class="apiresult"></pre>
    </form>
  </aside>
  <section class="messagepane">
    <h3>
      <?php if ($search): ?>
        Search results for &quot;<?= htmlspecialchars($search) ?>&quot;
      <?php elseif ($currentFolder): ?>
        <?= htmlspecialchars($currentFolder['name']) ?>
      <?php else: ?>
        Select a folder
      <?php endif; ?>
    </h3>
    <?php if (!$messages): ?>
      <p class="empty">No messages.</p>
    <?php else: ?>
      <table class="messagelist">
        <thead><tr><th></th><th>From</th><th>Subject</th><th>When</th></tr></thead>
        <tbody>
          <?php foreach ($messages as $m): ?>
            <tr class="<?= $m['is_read'] ? '' : 'unread' ?>">
              <td><?php if ($m['is_starred']): ?><span title="starred">&#9733;</span><?php endif; ?></td>
              <td><?= htmlspecialchars($m['from_name'] ?: $m['from_address']) ?></td>
              <td><a href="/mail/message/<?= (int)$m['id'] ?>"><?= htmlspecialchars($m['subject']) ?></a></td>
              <td><?= htmlspecialchars($m['created_at']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
</div>