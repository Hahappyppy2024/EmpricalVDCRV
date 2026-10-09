<?php $page_title = $page_title ?? 'Account'; ?>
<section>
  <h1>My account</h1>
  <p>Signed in as <strong><?= htmlspecialchars($profile_user['display_name'], ENT_QUOTES, 'UTF-8') ?></strong> (<?= htmlspecialchars($profile_user['role'], ENT_QUOTES, 'UTF-8') ?>).</p>
  <form method="post" action="/account" class="form">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="_method" value="PATCH">
    <label>Display name <input type="text" name="display_name" value="<?= htmlspecialchars($profile_user['display_name'], ENT_QUOTES, 'UTF-8') ?>" required></label>
    <label>Email <input type="email" value="<?= htmlspecialchars($profile_user['email'], ENT_QUOTES, 'UTF-8') ?>" disabled></label>
    <button class="btn btn-primary" type="submit">Update profile</button>
  </form>
  <p><a href="/password/forgot">Reset password</a></p>
</section>