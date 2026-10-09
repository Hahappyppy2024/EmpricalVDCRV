<?php
$user = $user ?? null;
$pageTitle = $pageTitle ?? 'Mail Server / Admin Console';
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?= htmlspecialchars($pageTitle) ?> &middot; <?= htmlspecialchars($appName ?? 'Mail Server / Admin Console') ?></title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<header class="topbar">
  <div class="brand"><a href="/dashboard"><?= htmlspecialchars($appName ?? 'Mail Server') ?></a></div>
  <?php if ($user): ?>
    <nav class="mainnav">
      <a href="/dashboard">Dashboard</a>
      <a href="/mail">Mailbox</a>
      <a href="/mail/compose">Compose</a>
      <a href="/mail/contacts">Contacts</a>
      <a href="/mail/rules">Filters</a>
      <?php if (in_array($user['role'], ['domain_admin','system_admin'], true)): ?>
        <a href="/mail/domains">Domains</a>
        <a href="/mail/quarantine">Quarantine</a>
      <?php endif; ?>
      <?php if ($user['role'] === 'system_admin'): ?>
        <a href="/mail/audit">Audit</a>
      <?php endif; ?>
      <a href="/mail/import-export">Import/Export</a>
      <a href="/mail/api-errors">API Errors</a>
    </nav>
    <div class="userbox">
      <span class="role-badge role-<?= htmlspecialchars($user['role']) ?>"><?= htmlspecialchars($user['role']) ?></span>
      <span><?= htmlspecialchars($user['username']) ?></span>
      <a href="/logout">Sign out</a>
    </div>
  <?php else: ?>
    <div class="userbox">
      <a href="/login">Sign in</a>
      <a href="/register">Register</a>
    </div>
  <?php endif; ?>
</header>
<main class="content">
<?= $body ?? '' ?>
</main>
<footer class="footer">P13 Mail Server / Admin Console &middot; demo build</footer>
<script src="/assets/js/app.js"></script>
</body>
</html>