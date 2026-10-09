<?php $page_title = $page_title ?? 'Login'; ?>
<section>
  <h1>Sign in</h1>
  <form method="post" action="/login" class="form">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
    <label>Email <input type="email" name="email" required></label>
    <label>Password <input type="password" name="password" required></label>
    <button class="btn btn-primary" type="submit">Sign in</button>
  </form>
  <p><a href="/register">Create an account</a> · <a href="/password/forgot">Forgot password</a></p>
  <div class="hint">
    <strong>Seeded accounts:</strong>
    <ul>
      <li>admin@example.com / Admin#12345</li>
      <li>seller@example.com / Seller#12345</li>
      <li>moderator@example.com / Moderator#12345</li>
      <li>customer@example.com / Customer#12345</li>
    </ul>
  </div>
</section>