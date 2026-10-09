<?php /** @var \LMS\Http\View $view */ ?>
<section class="card narrow">
    <h1>Forgot password</h1>
    <form method="post" action="/forgot">
        <input type="hidden" name="_csrf" value="<?= $view->e($csrf) ?>">
        <label>Username or email
            <input name="identity" value="<?= $view->e($identity) ?>" required>
        </label>
        <button class="btn btn-primary" type="submit">Request reset</button>
    </form>
    <?php if ($token): ?>
        <p class="banner banner-success">Reset token (offline): <code><?= $view->e($token) ?></code></p>
        <p>Visit <a href="/reset/<?= $view->e($token) ?>">/reset/&lt;token&gt;</a> to complete the reset.</p>
    <?php endif; ?>
</section>
