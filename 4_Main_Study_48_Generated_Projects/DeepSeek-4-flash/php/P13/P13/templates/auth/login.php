<div class="auth-wrap">
    <div class="auth-card">
        <h1>Sign in</h1>
        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post" action="/login">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <label>Username or email
                <input type="text" name="login" required autofocus placeholder="alice or alice@example.com">
            </label>
            <label>Password
                <input type="password" name="password" required>
            </label>
            <button type="submit" class="btn btn-block">Sign in</button>
        </form>
        <p class="muted">Seed account: <code>alice</code> / <code>Passw0rd!</code></p>
        <p><a href="/register">Create an account</a></p>
    </div>
</div>
