<?php $page_title = $page_title ?? 'Reset password'; ?>
<section>
  <h1>Reset password</h1>
  <form method="post" action="/password/reset" class="form">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
    <label>Reset token <input type="text" name="token" value="<?= htmlspecialchars($token ?? '', ENT_QUOTES, 'UTF-8') ?>" required></label>
    <label>New password <input type="password" name="password" minlength="8" required></label>
    <button class="btn btn-primary" type="submit">Update password</button>
  </form>
</section>