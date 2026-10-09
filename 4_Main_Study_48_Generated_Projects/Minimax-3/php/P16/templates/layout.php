<?php
/** @var array $session */
$session = $session ?? null;
$title = $title ?? 'P16';
$flash = $flash ?? ($_SESSION['flash'] ?? null);
if ($flash !== null && isset($_SESSION['flash'])) {
    unset($_SESSION['flash']);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars($title) ?> — <?= htmlspecialchars($app_name ?? 'P16') ?></title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<nav class="topnav">
    <div class="brand">
        <a href="/dashboard">P16</a>
        <span class="muted"><?= htmlspecialchars($app_name ?? '') ?></span>
    </div>
    <?php if ($session): ?>
    <ul class="navlinks">
        <li><a href="/dashboard">Dashboard</a></li>
        <li><a href="/logs">Logs</a></li>
        <li><a href="/services">Services</a></li>
        <li><a href="/jobs">Jobs</a></li>
        <li><a href="/job_runs">History</a></li>
        <li><a href="/backups">Backups</a></li>
        <li><a href="/alerts">Alerts</a></li>
        <li><a href="/health_targets">Health</a></li>
        <?php if ($session['role'] === 'admin'): ?>
            <li><a href="/configuration">Configuration</a></li>
            <li><a href="/api_tokens">API tokens</a></li>
            <li><a href="/audit">Audit</a></li>
        <?php endif; ?>
    </ul>
    <div class="userinfo">
        <span class="role role-<?= htmlspecialchars($session['role']) ?>"><?= htmlspecialchars($session['role']) ?></span>
        <span><?= htmlspecialchars($session['full_name']) ?></span>
        <form method="post" action="/logout" class="inline">
            <button type="submit" class="btn btn-link">Logout</button>
        </form>
    </div>
    <?php endif; ?>
</nav>
<main class="page">
    <?php if ($flash): ?>
        <div class="flash flash-<?= htmlspecialchars($flash['kind'] ?? 'info') ?>">
            <?= htmlspecialchars($flash['msg'] ?? '') ?>
        </div>
    <?php endif; ?>
    <?= $body ?? '' ?>
</main>
<footer class="footer muted">
    P16 — Server Monitoring &amp; Job Control Panel · PHP 8.3 · Slim 4 · SQLite
</footer>
<script src="/assets/app.js"></script>
</body>
</html>