<section class="card">
  <h1>Account</h1>
  <ul class="kv">
    <li><span>Username</span><b><?= htmlspecialchars($user['username']) ?></b></li>
    <li><span>Email</span><b><?= htmlspecialchars($user['email']) ?></b></li>
    <li><span>Full name</span><b><?= htmlspecialchars($user['full_name'] ?: '—') ?></b></li>
    <li><span>Role</span><b><?= htmlspecialchars($user['role']) ?></b></li>
    <li><span>Plan</span><b><?= htmlspecialchars((string)($user['plan_id'] ?? '—')) ?></b></li>
    <li><span>Active</span><b><?= $user['is_active'] ? 'yes' : 'no' ?></b></li>
    <li><span>Joined</span><b><?= htmlspecialchars($user['created_at']) ?></b></li>
  </ul>
</section>

<section class="card">
  <h2>Change password</h2>
  <form method="post" action="/account/password" class="form">
    <label>Current password<input type="password" name="current_password" required></label>
    <label>New password<input type="password" name="new_password" required minlength="8"></label>
    <label>Confirm new password<input type="password" name="new_password_confirm" required minlength="8"></label>
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['csrf'] ?? '') ?>">
    <button class="btn" type="submit">Update password</button>
  </form>
</section>