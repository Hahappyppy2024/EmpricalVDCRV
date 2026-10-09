<?php
/** @var array|null $user */ ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>AetherPanel — Hosting Control Panel</title>
<link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<?php if (!empty($user)): ?>
<header class="topbar">
  <div class="brand"><a href="/dashboard">AetherPanel</a></div>
  <nav class="nav">
    <a href="/dashboard">Dashboard</a>
    <a href="/domains">Domains</a>
    <a href="/sites">Sites</a>
    <a href="/files">Files</a>
    <a href="/databases">Databases</a>
    <a href="/backups">Backups</a>
    <a href="/ssl">SSL</a>
    <a href="/cron">Cron</a>
    <a href="/resources">Resources</a>
    <a href="/tickets">Tickets</a>
    <a href="/audit">Audit</a>
    <?php if (($user['role'] ?? '') === 'admin'): ?><a href="/admin">Admin</a><?php endif; ?>
  </nav>
  <div class="userbox">
    <span class="role role-<?= htmlspecialchars($user['role']) ?>"><?= htmlspecialchars($user['role']) ?></span>
    <a href="/account"><?= htmlspecialchars($user['full_name'] ?: $user['username']) ?></a>
    <form method="post" action="/logout" class="inline">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
      <button class="link" type="submit">Logout</button>
    </form>
  </div>
</header>
<?php endif; ?>
<main class="container">
  <?php if (!empty($flash)): ?>
    <?php foreach ($flash as $f): ?>
      <div class="flash flash-<?= htmlspecialchars($f['type']) ?>"><?= htmlspecialchars($f['message']) ?></div>
    <?php endforeach; ?>
  <?php endif; ?>
  <?php if (!empty($error)): ?>
    <div class="flash flash-error">Error: <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?= $content ?>
</main>
<footer class="footer">
  <small>AetherPanel &mdash; synthetic hosting control panel demo.</small>
</footer>
<script src="/assets/app.js"></script>
</body>
</html>