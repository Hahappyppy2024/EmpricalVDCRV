<?php $page_title = 'Register'; ?>
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
        <h1>Create account</h1>
        <?php if (!empty($error)): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="post" action="/register">
            <label>Full name</label>
            <input type="text" name="full_name" value="<?= htmlspecialchars($form['full_name'] ?? '') ?>" required>
            <label>Username</label>
            <input type="text" name="username" value="<?= htmlspecialchars($form['username'] ?? '') ?>" required>
            <label>Email</label>
            <input type="email" name="email" value="<?= htmlspecialchars($form['email'] ?? '') ?>" required>
            <label>Password (min 8 characters)</label>
            <input type="password" name="password" required>
            <div style="margin-top:1rem"><button class="btn" type="submit">Register</button></div>
        </form>
        <div class="auth-links">Already registered? <a href="/login">Sign in</a></div>
    </div>
</div>
</body>
</html>
