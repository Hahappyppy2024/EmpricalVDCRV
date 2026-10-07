<?php
$user = $_SESSION['user'] ?? null;
?><!doctype html>
<html>
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title><?= h($title ?? 'EduCollab') ?></title>
    <style>
        body { font-family: sans-serif; margin: 0; background: #f7f7f9; color: #222; }
        header { background: #2d4ea2; color: #fff; padding: 12px 18px; }
        header a { color: #fff; text-decoration: none; margin-right: 12px; }
        main { max-width: 980px; margin: 24px auto; padding: 0 16px; }
        .card { background: #fff; border-radius: 8px; padding: 16px; box-shadow: 0 1px 4px rgba(0,0,0,.08); margin-bottom: 16px; }
        label { display:block; margin-top:10px; font-weight:600; }
        input, textarea, select { width: 100%; padding: 8px; margin-top: 4px; box-sizing: border-box; }
        table { width:100%; border-collapse: collapse; }
        th, td { border:1px solid #ddd; padding:8px; text-align:left; vertical-align:top; }
        .muted { color:#666; font-size: .95em; }
        .row { display:flex; gap:16px; flex-wrap:wrap; }
        .row > * { flex:1 1 280px; }
        form.inline { display:inline; }
    </style>
</head>
<body>
<header>
    <a href="/courses">EduCollab</a>
    <?php if ($user): ?>
        <span style="margin-right:12px;">Hello, <?= h($user['display_name'] ?? $user['username'] ?? 'user') ?></span>
        <a href="/me">Profile</a>
        <a href="/me/submissions">My Submissions</a>
        <a href="/me/quiz-attempts">My Quiz Attempts</a>
        <?php if (!empty($user['is_global_admin'])): ?>
            <a href="/admin/users">Admin Users</a>
        <?php endif; ?>
        <form class="inline" method="post" action="/logout" style="display:inline;">
            <?= csrf_input() ?>
            <button type="submit">Logout</button>
        </form>
    <?php else: ?>
        <a href="/login">Login</a>
        <a href="/register">Register</a>
    <?php endif; ?>
</header>
<main>
    <?php include $viewFile; ?>
</main>
</body>
</html>