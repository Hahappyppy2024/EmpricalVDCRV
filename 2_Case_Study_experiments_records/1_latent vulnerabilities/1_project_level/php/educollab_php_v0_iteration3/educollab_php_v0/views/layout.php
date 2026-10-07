<!doctype html>
<html>
<head>
    <meta charset="utf-8" />
    <title><?= h($title ?? 'EduCollab') ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 30px; }
        nav a { margin-right: 10px; }
        .card { border: 1px solid #ddd; padding: 16px; margin: 12px 0; border-radius: 8px; }
        input, textarea, select { width: 100%; padding: 8px; margin: 6px 0 12px; }
        table { border-collapse: collapse; width: 100%; }
        td, th { border: 1px solid #ddd; padding: 8px; }
        .muted { color: #666; font-size: 0.9em; }
        form { margin: 0; }
    </style>
</head>
<body>
<nav>
    <a href="/courses">Courses</a>
    <a href="/memberships">My Memberships</a>
    <a href="/my/submissions">My Submissions</a>
    <a href="/my/quizzes">My Quiz Attempts</a>
    <?php $user = $_SESSION['user'] ?? null; ?>
    <?php if ($user): ?>
        <?php if (is_global_admin($user)): ?>
            <a href="/admin/users">Admin: Users</a>
        <?php endif; ?>
        <form style="display:inline" method="post" action="/logout"><?= csrf_input() ?><button type="submit">Logout</button></form>
        <span class="muted">Logged in as <?= h($user['username'] ?? '') ?></span>
    <?php else: ?>
        <a href="/login">Login</a>
        <a href="/register">Register</a>
    <?php endif; ?>
</nav>

<?= $content ?? '' ?>
</body>
</html>