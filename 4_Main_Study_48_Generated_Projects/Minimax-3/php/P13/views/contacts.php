<?php $pageTitle = 'Contacts'; ?>
<div class="card">
  <h1>Contacts</h1>
  <?php if (!empty($flash['ok'])): ?><div class="flash ok"><?= htmlspecialchars($flash['msg']) ?></div><?php endif; ?>
  <?php if (!empty($flash['ok']) === false && !empty($flash['msg'])): ?><div class="flash error"><?= htmlspecialchars($flash['msg']) ?></div><?php endif; ?>
  <?php if (!empty($_GET['created'])): ?><div class="flash ok">Contact created.</div><?php endif; ?>
  <?php if (!empty($_GET['updated'])): ?><div class="flash ok">Contact updated.</div><?php endif; ?>
  <?php if (!empty($_GET['deleted'])): ?><div class="flash ok">Contact deleted.</div><?php endif; ?>
  <form method="get" action="/mail/contacts" class="searchbar">
    <input type="text" name="q" value="<?= htmlspecialchars($q ?? '') ?>" placeholder="Search contacts">
    <button type="submit">Search</button>
  </form>
  <form method="post" action="/mail/contacts">
    <div class="grid two">
      <label>Name<input type="text" name="name" required></label>
      <label>Email<input type="email" name="email" required></label>
      <label>Organization<input type="text" name="organization"></label>
      <label>Phone<input type="text" name="phone"></label>
    </div>
    <label>Notes<input type="text" name="notes"></label>
    <button type="submit">Add contact</button>
  </form>
  <h3>My contacts</h3>
  <?php if (!$items): ?>
    <p class="empty">No contacts yet.</p>
  <?php else: ?>
    <table>
      <thead><tr><th>Name</th><th>Email</th><th>Org</th><th>Phone</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($items as $c): ?>
          <tr>
            <td><?= htmlspecialchars($c['name']) ?></td>
            <td><?= htmlspecialchars($c['email']) ?></td>
            <td><?= htmlspecialchars($c['organization'] ?? '') ?></td>
            <td><?= htmlspecialchars($c['phone'] ?? '') ?></td>
            <td>
              <form method="post" action="/mail/contacts/<?= (int)$c['id'] ?>/delete" style="display:inline">
                <button class="danger" type="submit" onclick="return confirm('Delete this contact?')">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>