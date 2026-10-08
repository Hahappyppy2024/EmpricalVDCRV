<div class="auth-wrap">
  <div class="card">
    <h1>Create account</h1>
    <?php if (!empty($flash)): ?>
      <div class="flash"><?= $this->e($flash) ?></div>
    <?php endif; ?>
    <form method="post" action="/register">
      <div class="form-grid">
        <div class="form-row">
          <label for="full_name">Full name</label>
          <input type="text" id="full_name" name="full_name" required>
        </div>
        <div class="form-row">
          <label for="username">Username</label>
          <input type="text" id="username" name="username" required>
        </div>
        <div class="form-row">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" required>
        </div>
        <div class="form-row">
          <label for="password">Password (min 8 characters)</label>
          <input type="password" id="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary">Create account</button>
      </div>
    </form>
    <p style="margin-bottom:0"><a href="/login">Back to sign in</a></p>
  </div>
</div>
