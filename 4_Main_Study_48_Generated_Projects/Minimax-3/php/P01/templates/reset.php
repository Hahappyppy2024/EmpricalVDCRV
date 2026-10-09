<?php /** @var \LMS\Http\View $view */ ?>
<section class="card narrow">
    <h1>Reset password</h1>
    <?php if ($ok): ?>
        <p class="banner banner-success">Password updated. <a href="/login">Sign in</a>.</p>
    <?php else: ?>
        <form method="post" action="/reset/<?= $view->e($token) ?>">
            <input type="hidden" name="_csrf" value="<?= $view->e($csrf) ?>">
            <label>New password (min 8 chars)
                <input type="password" name="password" required>
            </label>
            <?php if (!empty($error)): ?><div class="form-error"><?= $view->e($error) ?></div><?php endif; ?>
            <button class="btn btn-primary" type="submit">Update password</button>
        </form>
    <?php endif; ?>
</section>
