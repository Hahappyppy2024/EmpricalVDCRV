<h1>Sign in / Register / Recover</h1>

<section class="card">
  <h2>Sign in</h2>
  <?php if (!empty($login_error)): ?><div class="alert error"><?= htmlspecialchars($login_error) ?></div><?php endif; ?>
  <form action="/login" method="post">
    <label>Username or email
        <input type="text" name="username" required>
    </label>
    <label>Password
        <input type="password" name="password" required>
    </label>
    <button type="submit" class="btn">Sign in</button>
  </form>
</section>

<section class="card">
  <h2>Register</h2>
  <?php if (!empty($register_error)): ?><div class="alert error"><?= htmlspecialchars($register_error) ?></div><?php endif; ?>
  <?php if (!empty($register_success)): ?><div class="alert success"><?= htmlspecialchars($register_success) ?></div><?php endif; ?>
  <form action="/register" method="post">
    <label>Username <input type="text" name="username" required></label>
    <label>Email <input type="email" name="email" required></label>
    <label>Display name <input type="text" name="display_name" required></label>
    <label>Password (min 6 chars) <input type="password" name="password" required></label>
    <label>Role
        <select name="role">
            <option value="author">Author</option>
            <option value="reviewer">Reviewer</option>
        </select>
    </label>
    <button type="submit" class="btn">Create account</button>
  </form>
</section>

<section class="card">
  <h2>Reset password</h2>
  <?php if (!empty($reset_error)): ?><div class="alert error"><?= htmlspecialchars($reset_error) ?></div><?php endif; ?>
  <?php if (!empty($reset_success)): ?><div class="alert success"><?= nl2br(htmlspecialchars($reset_success)) ?></div><?php endif; ?>
  <form action="/reset/request" method="post">
    <label>Username or email <input type="text" name="identifier" required></label>
    <button type="submit" class="btn">Request reset token</button>
  </form>
  <form action="/reset/perform" method="post">
    <label>Reset token <input type="text" name="token" required></label>
    <label>New password <input type="password" name="new_password" required></label>
    <button type="submit" class="btn">Perform reset</button>
  </form>
</section>