<div class="auth-wrap">
    <div class="auth-card">
        <h1>Create account</h1>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $err): ?>
                    <div><?= e($err) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <form method="post" action="/register">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <label>Username
                <input type="text" name="username" required>
            </label>
            <label>Email
                <input type="email" name="email" required>
            </label>
            <label>Display name
                <input type="text" name="display_name">
            </label>
            <label>Password (min 8 chars)
                <input type="password" name="password" required minlength="8">
            </label>
            <button type="submit" class="btn btn-block">Register</button>
        </form>
        <p><a href="/login">Already have an account? Sign in</a></p>
    </div>
</div>
