<?php /** @var string $mode login|register */ ?>
<div class="auth-card">
    <?php if (!empty($error)): ?>
        <div class="flash flash-error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($mode === 'login'): ?>
        <form method="post" action="/login" class="form">
            <label>Username or email
                <input type="text" name="username" value="<?= e($old['username'] ?? '') ?>" required>
            </label>
            <label>Password
                <input type="password" name="password" required>
            </label>
            <button class="btn btn-primary" type="submit">Sign in</button>
        </form>
        <p class="muted">No account? <a href="/register">Create one</a></p>
    <?php else: ?>
        <form method="post" action="/register" class="form">
            <label>Full name
                <input type="text" name="full_name" value="<?= e($old['full_name'] ?? '') ?>">
            </label>
            <label>Username
                <input type="text" name="username" value="<?= e($old['username'] ?? '') ?>" required>
            </label>
            <label>Email
                <input type="email" name="email" value="<?= e($old['email'] ?? '') ?>" required>
            </label>
            <label>Password (min 8 characters)
                <input type="password" name="password" required>
            </label>
            <label>Confirm password
                <input type="password" name="password_confirm" required>
            </label>
            <button class="btn btn-primary" type="submit">Create account</button>
        </form>
        <p class="muted">Already registered? <a href="/login">Sign in</a></p>
    <?php endif; ?>

    <details class="seed-hint">
        <summary>Seed accounts</summary>
        <table class="table">
            <tr><th>Role</th><th>Login</th><th>Password</th></tr>
            <tr><td>admin</td><td>admin@example.com</td><td>Admin@123</td></tr>
            <tr><td>support</td><td>support@example.com</td><td>Support@123</td></tr>
            <tr><td>customer</td><td>alice@example.com</td><td>Alice@123</td></tr>
            <tr><td>customer</td><td>bob@example.com</td><td>Bob@123</td></tr>
        </table>
    </details>
</div>
