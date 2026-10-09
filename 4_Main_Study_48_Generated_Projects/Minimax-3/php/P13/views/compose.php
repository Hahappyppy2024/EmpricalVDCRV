<?php $pageTitle = 'Compose'; ?>
<div class="card">
  <h1>Compose</h1>
  <?php if (!empty($result['ok'])): ?><div class="flash ok">Saved draft #<?= (int)$result['id'] ?>.</div><?php endif; ?>
  <?php if (!empty($result['ok']) && isset($result['state']) && $result['state'] === 'draft_saved'): ?>
    <div class="flash ok">Draft saved.</div>
  <?php endif; ?>
  <?php if (!empty($result['errors'])): ?>
    <div class="flash error">
      <?php foreach ($result['errors'] as $field => $msg): ?>
        <div><strong><?= htmlspecialchars($field) ?>:</strong> <?= htmlspecialchars((string)$msg) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <form method="post" action="/mail/compose">
    <label>To (comma separated)
      <input type="text" name="to_addresses" value="<?= htmlspecialchars($prefill['to'] ?? '') ?>" required>
    </label>
    <label>CC
      <input type="text" name="cc_addresses">
    </label>
    <label>Subject
      <input type="text" name="subject" value="<?= htmlspecialchars($prefill['subject'] ?? '') ?>" required>
    </label>
    <label>Body
      <textarea name="body_text" rows="10" required><?= htmlspecialchars($prefill['body_text'] ?? '') ?></textarea>
    </label>
    <div class="actions">
      <button type="submit" name="action" value="send">Send</button>
      <button type="submit" name="action" value="save_draft" class="secondary">Save draft</button>
    </div>
  </form>
  <h3>Drafts</h3>
  <?php if (!$drafts): ?>
    <p class="empty">No drafts.</p>
  <?php else: ?>
    <ul class="drafts">
      <?php foreach ($drafts as $d): ?>
        <li>#<?= (int)$d['id'] ?> &middot; <?= htmlspecialchars($d['subject'] ?? '(no subject)') ?> &middot; <?= htmlspecialchars($d['updated_at']) ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>