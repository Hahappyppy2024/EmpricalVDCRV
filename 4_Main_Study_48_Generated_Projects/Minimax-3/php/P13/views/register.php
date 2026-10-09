<?php $pageTitle = 'Register'; ?>
<div class="card narrow">
  <h1>Create an account</h1>
  <?php if (!empty($error)): ?><div class="flash error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="post" action="/register">
    <label>Username
      <input type="text" name="username" value="<?= htmlspecialchars($old['username'] ?? '') ?>" required>
    </label>
    <label>Email
      <input type="email" name="email" value="<?= htmlspecialchars($old['email'] ?? '') ?>" required>
    </label>
    <label>Full name
      <input type="text" name="full_name" value="<?= htmlspecialchars($old['fullName'] ?? '') ?>">
    </label>
    <label>Password (8+ chars)
      <input type="password" name="password" minlength="8" required>
    </label>
    <button type="submit">Register</button>
  </form>
  <p>Have an account? <a href="/login">Sign in</a>.</p>
</div>