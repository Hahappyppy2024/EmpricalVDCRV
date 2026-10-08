<?php $page_title = 'Sign in'; ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($page_title) ?> · <?= htmlspecialchars($this->data['app_name'] ?? 'CloudFS') ?></title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card">
        <h1><?= htmlspecialchars($this->data['app_name'] ?? 'CloudFS') ?></h1>
        <p class="meta">Sign in to access your files, shares and team spaces.</p>
        <?php if (!empty($error)): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="post" action="/login">
            <label>Username or email</label>
            <input type="text" name="identifier" value="<?= htmlspecialchars($form['identifier'] ?? '') ?>" required autofocus>
            <label>Password</label>
            <input type="password" name="password" required>
            <div style="margin-top:1rem"><button class="btn" type="submit">Sign in</button></div>
        </form>
        <div class="auth-links">
            No account? <a href="/register">Register</a> · <a href="/reset">Forgot password</a>
        </div>
    </div>
    <p class="meta" style="text-align:center;margin-top:1rem">Seed accounts: <strong>admin</strong>/admin123 · <strong>alice</strong>/password123 · <strong>bob</strong>/password123</p>
</div>
</body>
</html>
