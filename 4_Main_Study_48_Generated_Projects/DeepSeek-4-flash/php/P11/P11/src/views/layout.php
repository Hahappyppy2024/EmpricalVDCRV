<?php
/**
 * Global layout. Expected variables:
 *   $pageTitle, $activeNav, $content, $user, $flash
 */
$panelName = (new \App\Repository\SettingRepository())->get('panel_name', 'P11 Hosting Control Panel');
$nav = [];
if ($user !== null) {
    $nav['dashboard'] = ['/', 'Dashboard'];
    if ($user['role'] === 'customer') {
        $nav['domains'] = ['/domains', 'Domains'];
        $nav['sites'] = ['/sites', 'Sites'];
        $nav['files'] = ['/files', 'Files'];
        $nav['databases'] = ['/databases', 'Databases'];
        $nav['backups'] = ['/backups', 'Backups'];
        $nav['certificates'] = ['/certificates', 'SSL'];
        $nav['tasks'] = ['/cron', 'Cron'];
    }
    if ($user['role'] === 'support') {
        $nav['usage'] = ['/usage', 'Usage'];
    }
    $nav['tickets'] = ['/tickets', 'Tickets'];
    if ($user['role'] === 'customer' || $user['role'] === 'admin') {
        $nav['audit'] = ['/audit', 'Audit'];
    }
    if ($user['role'] === 'admin') {
        $nav['admin'] = ['/admin', 'Admin'];
        $nav['usage'] = ['/usage', 'Usage'];
    }
    $nav['account'] = ['/account', 'Account'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? 'Dashboard') ?> — <?= e($panelName) ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<header class="topbar">
    <a class="brand" href="/"><?= e($panelName) ?></a>
    <?php if ($user !== null): ?>
        <nav class="nav">
            <?php foreach ($nav as $key => $item): ?>
                <a class="nav-link <?= ($activeNav ?? '') === $key ? 'active' : '' ?>" href="<?= e($item[0]) ?>"><?= e($item[1]) ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="userbox">
            <span class="role-badge"><?= e($user['role']) ?></span>
            <span class="username"><?= e($user['username']) ?></span>
            <form method="post" action="/logout" class="inline">
                <button class="btn btn-small" type="submit">Sign out</button>
            </form>
        </div>
    <?php endif; ?>
</header>
<main class="container">
    <?php if (!empty($flash['success'])): ?>
        <div class="flash flash-success"><?= e($flash['success']) ?></div>
    <?php endif; ?>
    <?php if (!empty($flash['error'])): ?>
        <div class="flash flash-error"><?= e($flash['error']) ?></div>
    <?php endif; ?>
    <h1><?= e($pageTitle ?? '') ?></h1>
    <?= $content ?>
</main>
<footer class="footer">
    <span><?= e($panelName) ?> — synthetic benchmark application</span>
</footer>
<script src="/assets/js/app.js"></script>
</body>
</html>
