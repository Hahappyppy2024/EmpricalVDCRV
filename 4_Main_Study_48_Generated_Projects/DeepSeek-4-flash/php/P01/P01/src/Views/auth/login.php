<?php
/** @var string $error */
?>
<div class="auth-card">
  <h1>Sign in</h1>
  <?php if ($error !== ''): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="post" action="/login">
    <label>Username or email <input type="text" name="identifier" required autofocus></label>
    <label>Password <input type="password" name="password" required></label>
    <button type="submit" class="btn btn-primary">Sign in</button>
  </form>
  <p class="muted">No account? <a href="/register">Register</a> &middot; <a href="/forgot-password">Forgot password?</a></p>
  <p class="muted small">Demo: admin / AdminPass123! &middot; alice / InstructorPass123! &middot; student1 / StudentPass123!</p>
</div>
