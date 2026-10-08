<?php
/** @var string $error */
/** @var string $token */
?>
<div class="auth-card">
  <h1>Choose a new password</h1>
  <?php if ($error !== ''): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="post" action="/reset-password">
    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
    <label>New password <input type="password" name="password" required minlength="8"></label>
    <button type="submit" class="btn btn-primary">Set password</button>
  </form>
</div>
