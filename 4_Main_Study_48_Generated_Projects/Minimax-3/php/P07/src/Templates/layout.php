<?php
/** @var string $csrf */
/** @var array|null $user */
/** @var string $app_name */
/** @var string $path */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($app_name) ?></title>
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf) ?>">
    <meta name="viewer" content="<?= $user ? htmlspecialchars($user['email']) : '' ?>">
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<header>
    <h1><a href="/"><?= htmlspecialchars($app_name) ?></a></h1>
    <nav>
        <a href="/dashboard">Dashboard</a>
        <a href="/upload">Upload</a>
        <a href="/folders">Folders</a>
        <a href="/files">Files</a>
        <a href="/shares">Shares</a>
        <a href="/teams">Teams</a>
        <a href="/search">Search</a>
        <a href="/trash">Trash</a>
        <a href="/quota">Quota</a>
        <a href="/activity">Activity</a>
        <?php if (!empty($is_admin)): ?><a href="/admin">Admin</a><?php endif; ?>
    </nav>
    <div class="auth">
        <?php if ($user): ?>
            <span><?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['role']) ?>)</span>
            <form method="post" action="/api/file/account_access" class="api-form" data-method="post" data-action="logout">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" value="logout">
                <button type="submit">Sign out</button>
            </form>
        <?php else: ?>
            <a href="/login">Sign in</a>
            <a href="/register">Register</a>
        <?php endif; ?>
    </div>
</header>
<main>
<?= $content ?>
</main>
<footer>
    <p>Synthetic benchmark project P07 &mdash; Slim 4 + SQLite</p>
</footer>
<div id="toast" class="toast"></div>
<script src="/assets/app.js"></script>
</body>
</html>