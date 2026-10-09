<section class="card">
  <h2>Sign in</h2>
  <?php if (!empty($alreadyLoggedIn)): ?>
    <p class="muted">You are already signed in.</p>
  <?php else: ?>
    <p class="muted">Try <code>admin / Password123!</code> or <code>alice / Password123!</code></p>
  <?php endif; ?>
  <form method="post" action="/login" class="form">
    <label>Username or email
      <input type="text" name="login" required autofocus>
    </label>
    <label>Password
      <input type="password" name="password" required>
    </label>
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '') ?>">
    <button class="btn" type="submit">Sign in</button>
  </form>
  <p class="muted">No account? <a href="/register">Register</a></p>
</section>