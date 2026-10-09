<?php $page_title = $page_title ?? 'Register'; ?>
<section>
  <h1>Create your account</h1>
  <form method="post" action="/register" class="form">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
    <label>Display name <input type="text" name="display_name" required></label>
    <label>Email <input type="email" name="email" required></label>
    <label>Password <input type="password" name="password" minlength="8" required></label>
    <label>Role
      <select name="role">
        <option value="customer">Customer</option>
        <option value="seller">Seller</option>
      </select>
    </label>
    <button class="btn btn-primary" type="submit">Register</button>
  </form>
</section>