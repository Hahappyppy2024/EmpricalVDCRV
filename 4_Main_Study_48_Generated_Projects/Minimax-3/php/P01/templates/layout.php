<?php /** @var \LMS\Http\View $view */ ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $view->e($appName ?? 'P01 LMS') ?></title>
<link rel="stylesheet" href="<?= $view->asset('app.css') ?>">
</head>
<body>
<header class="topbar">
    <div class="container">
        <a href="/" class="brand"><?= $view->e($appName) ?></a>
        <nav class="primary">
            <?php if (!empty($user)): ?>
                <a href="/dashboard">Dashboard</a>
                <a href="/courses">Discover</a>
                <a href="/my/courses">My Courses</a>
                <?php if (in_array($user['role'], ['instructor', 'admin'], true)): ?>
                    <a href="/grades">Grades</a>
                    <a href="/exports">Exports</a>
                <?php endif; ?>
                <?php if ($user['role'] === 'admin'): ?>
                    <a href="/admin">Admin</a>
                <?php endif; ?>
                <a href="/frontend-api">Frontend API</a>
                <a href="/error-demo/notfound">Errors</a>
                <span class="muted"><?= $view->e($user['full_name']) ?> · <?= $view->e($user['role']) ?></span>
                <a class="btn btn-ghost" href="/logout">Sign out</a>
            <?php else: ?>
                <a href="/courses">Browse courses</a>
                <a href="/login">Sign in</a>
                <a class="btn btn-primary" href="/register">Register</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<?php if (!empty($flash['notice'])): ?>
    <div class="banner banner-success container"><?= $view->e($flash['notice']) ?></div>
<?php endif; ?>
<?php if (!empty($flash['error'])): ?>
    <div class="banner banner-error container"><?= $view->e($flash['error']) ?></div>
<?php endif; ?>

<main class="container">
<?= $content ?? '' ?>
</main>

<footer class="footer container">
    <small>Offline synthetic benchmark · PHP 8.3 · Slim 4 · SQLite</small>
</footer>
<script src="<?= $view->asset('app.js') ?>"></script>
</body>
</html>
