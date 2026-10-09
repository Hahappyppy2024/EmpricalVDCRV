<?php /** @var \LMS\Http\View $view */ ?>
<section class="card narrow">
    <h1>Sign in</h1>
    <form method="post" action="/login">
        <input type="hidden" name="_csrf" value="<?= $view->e($csrf) ?>">
        <label>Username or email
            <input name="identity" value="<?= $view->e($identity) ?>" required>
        </label>
        <label>Password
            <input type="password" name="password" required>
        </label>
        <?php if (!empty($error)): ?>
            <div class="form-error"><?= $view->e($error) ?></div>
        <?php endif; ?>
        <button class="btn btn-primary" type="submit">Sign in</button>
    </form>
    <p class="muted">No account? <a href="/register">Register</a> · <a href="/forgot">Forgot password</a></p>
</section>
