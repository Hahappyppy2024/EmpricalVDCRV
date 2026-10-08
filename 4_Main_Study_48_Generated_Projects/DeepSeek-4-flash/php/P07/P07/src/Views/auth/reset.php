<?php $page_title = 'Recover account'; ?>
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
        <h1>Recover account</h1>
        <?php if (!empty($notice)): ?><div class="alert alert-ok"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
        <?php if (!empty($error)): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="post" action="/reset">
            <label>Username or email</label>
            <input type="text" name="identifier" value="<?= htmlspecialchars($form['identifier'] ?? '') ?>" required>
            <div style="margin-top:1rem"><button class="btn" type="submit">Request password reset</button></div>
        </form>
        <div class="auth-links"><a href="/login">Back to sign in</a></div>
    </div>
</div>
</body>
</html>
