<?php $pageTitle = 'Password recovery'; ?>
<div class="auth-card card">
  <h1>Reset password</h1>
  <p class="muted">Enter your email and we will send a reset link (stored in the local mail log).</p>
  <?php if (!empty($notice)): ?>
    <div class="flash flash-ok"><?= e($notice) ?></div>
  <?php endif; ?>
  <form method="post" action="/forgot" class="form">
    <?= csrf_field($csrf) ?>
    <label>Email <input type="email" name="email" required></label>
    <button class="btn btn-primary btn-block" type="submit">Send reset link</button>
  </form>
  <p class="muted small"><a href="/login">Back to sign in</a></p>
</div>
