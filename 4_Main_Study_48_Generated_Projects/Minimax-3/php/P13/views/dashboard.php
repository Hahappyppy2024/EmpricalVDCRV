<?php $pageTitle = 'Dashboard'; $user = $user ?? []; ?>
<div class="card">
  <h1>Welcome, <?= htmlspecialchars($user['full_name'] ?? $user['username']) ?></h1>
  <p>Signed in as <strong><?= htmlspecialchars($user['username']) ?></strong> with role <span class="role-badge role-<?= htmlspecialchars($user['role']) ?>"><?= htmlspecialchars($user['role']) ?></span>.</p>
  <div class="grid two">
    <a class="tile" href="/mail"><h3>Mailbox</h3><p>Browse folders, messages, and unread counts.</p></a>
    <a class="tile" href="/mail/compose"><h3>Compose</h3><p>Send a simulated email or save drafts.</p></a>
    <a class="tile" href="/mail/contacts"><h3>Contacts</h3><p>Manage your personal contact list.</p></a>
    <a class="tile" href="/mail/rules"><h3>Filters &amp; Rules</h3><p>Define conditions and actions for incoming mail.</p></a>
    <a class="tile" href="/mail/attachments"><h3>Attachments</h3><p>Upload outgoing files and download received files.</p></a>
    <a class="tile" href="/mail/import-export"><h3>Import / Export</h3><p>Bulk contact import and CSV exports.</p></a>
    <?php if (in_array($user['role'], ['domain_admin', 'system_admin'], true)): ?>
      <a class="tile" href="/mail/domains"><h3>Domains</h3><p>Manage domains, aliases, quotas, mailboxes.</p></a>
      <a class="tile" href="/mail/quarantine"><h3>Quarantine</h3><p>Review quarantined messages.</p></a>
    <?php endif; ?>
    <?php if ($user['role'] === 'system_admin'): ?>
      <a class="tile" href="/mail/audit"><h3>Audit logs</h3><p>Review login, sending, rule, and domain events.</p></a>
    <?php endif; ?>
    <a class="tile" href="/mail/api-errors"><h3>Frontend API errors</h3><p>Simulate and log API error responses.</p></a>
  </div>
</div>