<?php $pageTitle = 'Message'; ?>
<div class="card">
  <?php if (!$message): ?>
    <h1>Message not found</h1>
    <p class="flash error"><?= htmlspecialchars($error ?? 'Unknown message') ?></p>
    <p><a href="/mail">Back to mailbox</a></p>
  <?php else: ?>
    <?php if (!empty($flash)): ?><div class="flash ok"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
    <h1><?= htmlspecialchars($message['subject']) ?></h1>
    <div class="msgmeta">
      <div><strong>From:</strong> <?= htmlspecialchars(($message['from_name'] ?? '') . ' <' . $message['from_address'] . '>') ?></div>
      <div><strong>To:</strong> <?= htmlspecialchars($message['to_addresses']) ?></div>
      <?php if (!empty($message['cc_addresses'])): ?><div><strong>CC:</strong> <?= htmlspecialchars($message['cc_addresses']) ?></div><?php endif; ?>
      <div><strong>Folder:</strong> <?= htmlspecialchars($message['folder_name']) ?></div>
      <div><strong>Received:</strong> <?= htmlspecialchars($message['created_at']) ?></div>
    </div>
    <pre class="msgbody"><?= htmlspecialchars($message['body_text']) ?></pre>
    <h3>Attachments</h3>
    <?php if (!$attachments): ?>
      <p class="empty">No attachments.</p>
    <?php else: ?>
      <ul class="attachlist">
        <?php foreach ($attachments as $a): ?>
          <li><a href="/mail/attachments/<?= (int)$a['id'] ?>/download"><?= htmlspecialchars($a['filename']) ?></a> (<?= (int)$a['size_bytes'] ?> bytes, <?= htmlspecialchars($a['mime_type']) ?>)</li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <div class="actions">
      <form method="post" action="/api/mail/message_reading/<?= (int)$message['id'] ?>" class="api-call-form" data-method="PATCH">
        <input type="hidden" name="action" value="star">
        <button type="submit">Toggle star</button>
        <pre class="apiresult"></pre>
      </form>
      <form method="post" action="/api/mail/message_reading/<?= (int)$message['id'] ?>" class="api-call-form" data-method="PATCH">
        <input type="hidden" name="action" value="delete">
        <button type="submit" class="danger">Delete</button>
        <pre class="apiresult"></pre>
      </form>
    </div>
  <?php endif; ?>
</div>