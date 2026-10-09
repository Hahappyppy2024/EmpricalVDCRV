<section class="card">
  <h2>Register</h2>
  <form method="post" action="/register" class="form">
    <label>Username
      <input type="text" name="username" required value="<?= htmlspecialchars($old['username'] ?? '') ?>">
    </label>
    <label>Email
      <input type="email" name="email" required value="<?= htmlspecialchars($old['email'] ?? '') ?>">
    </label>
    <label>Full name
      <input type="text" name="full_name" value="<?= htmlspecialchars($old['fullName'] ?? '') ?>">
    </label>
    <label>Password
      <input type="password" name="password" required minlength="8">
    </label>
    <label>Confirm password
      <input type="password" name="password_confirm" required minlength="8">
    </label>
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '') ?>">
    <button class="btn" type="submit">Create account</button>
  </form>
  <p class="muted">Already registered? <a href="/login">Sign in</a></p>
</section>