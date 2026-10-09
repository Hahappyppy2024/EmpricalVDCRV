<?php $page_title = $page_title ?? 'Forgot password'; ?>
<section>
  <h1>Forgot password</h1>
  <form method="post" action="/password/forgot" class="form">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
    <label>Email <input type="email" name="email" required></label>
    <button class="btn btn-primary" type="submit">Send reset token</button>
  </form>
  <p>The token is logged to <code>data/outbox.log</code> for offline testing.</p>
</section>