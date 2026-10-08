<?php
/** @var string $error */
/** @var string $notice */
?>
<div class="auth-card">
  <h1>Reset password</h1>
  <?php if ($error !== ''): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>
  <?php if ($notice !== ''): ?><div class="alert alert-success"><?= $notice ?></div><?php endif; ?>
  <form method="post" action="/forgot-password">
    <label>Username or email <input type="text" name="identifier" required autofocus></label>
    <button type="submit" class="btn btn-primary">Send reset link</button>
  </form>
  <p class="muted"><a href="/login">&larr; Back to sign in</a></p>
</div>
