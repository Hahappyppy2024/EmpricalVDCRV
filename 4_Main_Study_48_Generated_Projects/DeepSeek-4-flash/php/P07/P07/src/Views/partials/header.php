<?php /** @var array|null $current_user */ ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($this->data['app_name'] ?? 'Cloud File-Sharing System') ?><?= isset($page_title) ? ' · ' . htmlspecialchars($page_title) : '' ?></title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body<?php if (isset($page_slug) && $page_slug !== ''): ?> data-page="<?= htmlspecialchars($page_slug) ?>"<?php endif; ?>>
<header class="topbar">
    <div class="brand"><a href="/"><?= htmlspecialchars($this->data['app_name'] ?? 'CloudFS') ?></a></div>
    <?php if (!empty($current_user)): ?>
    <nav class="nav">
        <a href="/dashboard">Files</a>
        <a href="/upload">Upload</a>
        <a href="/folders">Folders</a>
        <a href="/sharing">Sharing</a>
        <a href="/teams">Teams</a>
        <a href="/search">Search</a>
        <a href="/versions">Versions</a>
        <a href="/trash">Trash</a>
        <a href="/quota">Quota</a>
        <a href="/audit">Audit</a>
        <a href="/account-access">Account</a>
        <?php if (($current_user['role'] ?? 'user') === 'admin'): ?><a href="/admin">Admin</a><?php endif; ?>
    </nav>
    <div class="userbox">
        <span class="pill <?= ($current_user['role'] ?? 'user') === 'admin' ? 'pill-admin' : 'pill-user' ?>"><?= htmlspecialchars($current_user['username']) ?></span>
        <form method="post" action="/logout" class="inline"><button class="btn btn-ghost btn-sm" type="submit">Sign out</button></form>
    </div>
    <?php endif; ?>
</header>
<main class="container">
