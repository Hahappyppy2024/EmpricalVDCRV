<?php
namespace App\Views;
?>
<section class="card">
  <h1>Password reset</h1>
  <p class="muted">Step 1 â€” request a reset token (delivered to <code>storage/mail.log</code> by the local mail adapter and echoed in local mode).</p>
  <form data-api-form data-json="true" method="post" action="/auth/reset" class="stack" data-success-message="Reset token issued">
    <label>Email
      <input type="email" name="email" required>
    </label>
    <button class="btn btn--primary" type="submit">Request reset</button>
  </form>
</section>
<section class="card">
  <h2>Step 2 â€” confirm the reset</h2>
  <form data-api-form data-json="true" method="post" action="/auth/reset-confirm" data-redirect="/login" class="stack">
    <label>Email
      <input type="email" name="email" required>
    </label>
    <label>Reset token
      <input type="text" name="token" placeholder="token from the reset request" required>
    </label>
    <label>New password
      <input type="password" name="password" autocomplete="new-password" required>
    </label>
    <button class="btn btn--primary" type="submit">Reset password</button>
  </form>
</section>