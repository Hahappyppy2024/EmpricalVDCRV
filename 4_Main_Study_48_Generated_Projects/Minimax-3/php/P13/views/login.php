<?php $pageTitle = 'Sign in'; ?>
<div class="card narrow">
  <h1>Sign in</h1>
  <?php if (!empty($_GET['registered'])): ?><div class="flash ok">Registration succeeded. Please sign in.</div><?php endif; ?>
  <?php if (!empty($error)): ?><div class="flash error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="post" action="/login">
    <label>Username or email
      <input type="text" name="username" value="<?= htmlspecialchars($old['username'] ?? '') ?>" required>
    </label>
    <label>Password
      <input type="password" name="password" required>
    </label>
    <button type="submit">Sign in</button>
  </form>
  <p>No account? <a href="/register">Register</a>.</p>
  <details class="hint">
    <summary>Seed accounts</summary>
    <ul>
      <li>alice / Password123! (mail_user, example.com)</li>
      <li>bob / Password123! (mail_user, example.com)</li>
      <li>carol / Password123! (mail_user, acme.test)</li>
      <li>dadm / Password123! (domain_admin, example.com)</li>
      <li>sadm / Password123! (system_admin)</li>
    </ul>
  </details>
</div>