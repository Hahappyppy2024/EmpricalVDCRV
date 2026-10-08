<?php $pageTitle = 'Reset password'; ?>
<div class="auth-card card">
  <h1>Choose a new password</h1>
  <form method="post" action="/reset/<?= e($token) ?>" class="form">
    <?= csrf_field($csrf) ?>
    <label>New password (min 6 chars) <input type="password" name="password" required minlength="6"></label>
    <button class="btn btn-primary btn-block" type="submit">Reset password</button>
  </form>
</div>
