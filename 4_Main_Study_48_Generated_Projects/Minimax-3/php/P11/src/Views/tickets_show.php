<h1>Ticket #<?= (int)$ticket['id'] ?>: <?= htmlspecialchars($ticket['subject']) ?></h1>
<section class="card">
  <ul class="kv">
    <li><span>Status</span><b><span class="pill pill-<?= htmlspecialchars($ticket['status']) ?>"><?= htmlspecialchars($ticket['status']) ?></span></b></li>
    <li><span>Priority</span><b><?= htmlspecialchars($ticket['priority']) ?></b></li>
    <li><span>Customer</span><b><?= htmlspecialchars($ticket['customer_name'] ?? '') ?></b></li>
    <li><span>Created</span><b><?= htmlspecialchars($ticket['created_at']) ?></b></li>
    <li><span>Updated</span><b><?= htmlspecialchars($ticket['updated_at']) ?></b></li>
  </ul>
  <pre class="body"><?= htmlspecialchars($ticket['body']) ?></pre>
</section>

<section class="card">
  <h2>Conversation</h2>
  <?php foreach ($replies as $r): ?>
    <div class="reply reply-<?= htmlspecialchars($r['role']) ?>">
      <header>
        <b><?= htmlspecialchars($r['username']) ?></b>
        <span class="role role-<?= htmlspecialchars($r['role']) ?>"><?= htmlspecialchars($r['role']) ?></span>
        <time><?= htmlspecialchars($r['created_at']) ?></time>
      </header>
      <p><?= nl2br(htmlspecialchars($r['body'])) ?></p>
    </div>
  <?php endforeach; ?>
</section>

<section class="card">
  <h2>Reply</h2>
  <form method="post" action="/tickets/<?= (int)$ticket['id'] ?>/reply" class="form">
    <textarea name="body" rows="3" required></textarea>
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
    <button class="btn" type="submit">Send reply</button>
  </form>
</section>

<?php if (in_array($user['role'], ['support','admin'], true)): ?>
<section class="card">
  <h2>Status</h2>
  <form method="post" action="/tickets/<?= (int)$ticket['id'] ?>/status" class="form inline">
    <select name="status">
      <?php foreach (['open','pending','answered','closed'] as $st): ?>
        <option <?= $ticket['status'] === $st ? 'selected' : '' ?>><?= $st ?></option>
      <?php endforeach; ?>
    </select>
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
    <button class="btn" type="submit">Set status</button>
  </form>
</section>
<?php endif; ?>