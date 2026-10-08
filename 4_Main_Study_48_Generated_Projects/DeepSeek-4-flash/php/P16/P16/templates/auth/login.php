<div class="auth-wrap">
  <div class="card">
    <h1>Sign in</h1>
    <?php if (!empty($flash)): ?>
      <div class="flash"><?= $this->e($flash) ?></div>
    <?php endif; ?>
    <p class="sub" style="margin-top:0">Server Monitoring &amp; Job Control Panel</p>
    <form method="post" action="/login">
      <div class="form-grid">
        <div class="form-row">
          <label for="username">Username</label>
          <input type="text" id="username" name="username" autocomplete="username" required>
        </div>
        <div class="form-row">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" autocomplete="current-password" required>
        </div>
        <button type="submit" class="btn btn-primary">Sign in</button>
      </div>
    </form>
    <p style="margin-bottom:0;color:var(--muted);font-size:12px">
      Seed accounts: <code>admin/admin123</code> · <code>operator/operator123</code> · <code>viewer/viewer123</code>
      <br>New registrations receive the <code>operator</code> role. <a href="/register">Create an account</a>
    </p>
  </div>
</div>
