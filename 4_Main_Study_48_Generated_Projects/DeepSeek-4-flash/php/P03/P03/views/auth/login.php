<?php $pageTitle = 'Sign in'; ?>
<div class="auth-card card">
  <h1>Sign in</h1>
  <form method="post" action="/login" class="form">
    <?= csrf_field($csrf) ?>
    <label>Email <input type="email" name="email" required></label>
    <label>Password <input type="password" name="password" required></label>
    <button class="btn btn-primary btn-block" type="submit">Sign in</button>
  </form>
  <p class="muted small"><a href="/forgot">Forgot your password?</a> · <a href="/register">Create an account</a></p>
  <details class="seed">
    <summary>Seed accounts</summary>
    <ul>
      <li>customer1@example.com / customer123</li>
      <li>seller1@example.com / seller123</li>
      <li>seller2@example.com / seller123</li>
      <li>moderator1@example.com / moderator123</li>
      <li>admin@example.com / admin123</li>
    </ul>
  </details>
</div>
