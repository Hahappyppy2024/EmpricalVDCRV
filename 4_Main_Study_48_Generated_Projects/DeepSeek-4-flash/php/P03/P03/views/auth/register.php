<?php $pageTitle = 'Register'; ?>
<div class="auth-card card">
  <h1>Create an account</h1>
  <form method="post" action="/register" class="form" data-jsregister>
    <?= csrf_field($csrf) ?>
    <label>Full name <input type="text" name="name" required></label>
    <label>Email <input type="email" name="email" required></label>
    <label>Password (min 6 chars) <input type="password" name="password" required minlength="6"></label>
    <button class="btn btn-primary btn-block" type="submit">Register</button>
  </form>
  <p class="muted small">Already registered? <a href="/login">Sign in</a></p>
</div>
