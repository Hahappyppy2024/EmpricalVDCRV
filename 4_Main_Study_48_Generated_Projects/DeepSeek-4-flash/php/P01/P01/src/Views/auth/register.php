<?php
/** @var string $error */
?>
<div class="auth-card">
  <h1>Create account</h1>
  <?php if ($error !== ''): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="post" action="/register">
    <label>Username <input type="text" name="username" required minlength="3" autofocus></label>
    <label>Display name <input type="text" name="display_name" required minlength="2"></label>
    <label>Email <input type="email" name="email" required></label>
    <label>Password <input type="password" name="password" required minlength="8"></label>
    <button type="submit" class="btn btn-primary">Register</button>
  </form>
  <p class="muted">Already have an account? <a href="/login">Sign in</a></p>
</div>
