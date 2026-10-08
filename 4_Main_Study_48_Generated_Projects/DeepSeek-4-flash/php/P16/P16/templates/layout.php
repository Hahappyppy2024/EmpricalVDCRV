<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $this->e($title ?? 'P16 Monitor') ?> · P16 Monitor</title>
  <link rel="stylesheet" href="/assets/css/app.css">
  <script src="/assets/js/app.js" defer></script>
  <script>
    window.P16_WS_ENABLED = <?= ($ws['enabled'] ?? false) ? 'true' : 'false' ?>;
    window.P16_WS_URL = <?= json_encode($ws['url'] ?? '') ?>;
  </script>
</head>
<body>
<header class="topbar">
  <div class="brand"><span class="logo">&#9679;</span> P16 Monitor</div>
  <nav class="nav">
    <a href="/" class="nav-link <?= ($active ?? '') === '/' ? 'on' : '' ?>">Dashboard</a>
    <a href="/logs" class="nav-link <?= ($active ?? '') === '/logs' ? 'on' : '' ?>">Logs</a>
    <a href="/services" class="nav-link <?= ($active ?? '') === '/services' ? 'on' : '' ?>">Services</a>
    <a href="/jobs" class="nav-link <?= ($active ?? '') === '/jobs' ? 'on' : '' ?>">Jobs</a>
    <a href="/job-history" class="nav-link <?= ($active ?? '') === '/job-history' ? 'on' : '' ?>">Job History</a>
    <a href="/backups" class="nav-link <?= ($active ?? '') === '/backups' ? 'on' : '' ?>">Backups</a>
    <a href="/alerts" class="nav-link <?= ($active ?? '') === '/alerts' ? 'on' : '' ?>">Alerts</a>
    <a href="/health-checks" class="nav-link <?= ($active ?? '') === '/health-checks' ? 'on' : '' ?>">Health</a>
    <?php if (($user['role'] ?? '') === 'admin'): ?>
    <a href="/config" class="nav-link <?= ($active ?? '') === '/config' ? 'on' : '' ?>">Config</a>
    <a href="/tokens" class="nav-link <?= ($active ?? '') === '/tokens' ? 'on' : '' ?>">API Tokens</a>
    <a href="/audit" class="nav-link <?= ($active ?? '') === '/audit' ? 'on' : '' ?>">Audit</a>
    <?php endif; ?>
    <a href="/account" class="nav-link <?= ($active ?? '') === '/account' ? 'on' : '' ?>">Account</a>
  </nav>
  <div class="user">
    <span class="pill pill-<?= $this->e($user['role'] ?? 'viewer') ?>"><?= $this->e($user['role'] ?? '') ?></span>
    <span class="uname"><?= $this->e($user['full_name'] ?? $user['username'] ?? '') ?></span>
    <form method="post" action="/logout" class="inline">
      <button type="submit" class="btn btn-sm">Sign out</button>
    </form>
  </div>
</header>
<main class="container">
  <?= $content ?>
</main>
<div id="toasts" class="toasts"></div>
</body>
</html>
