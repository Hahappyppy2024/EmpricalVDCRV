<?php $pageTitle = 'My account';
$prefCats = '';
if (isset($profile['preferences']['categories'])) {
    $prefCats = is_array($profile['preferences']['categories'])
        ? implode(', ', $profile['preferences']['categories'])
        : (string) $profile['preferences']['categories'];
}
?>
<h1>My account</h1>
<div class="tabs">
  <a href="/account">Profile</a>
  <a href="/account/addresses">Addresses</a>
  <a href="/account/orders">Orders</a>
</div>

<div class="card form">
  <h2>Profile</h2>
  <form method="post" action="/account/profile">
    <?= csrf_field($csrf) ?>
    <label>Name <input type="text" name="name" value="<?= e($profile['name']) ?>" required></label>
    <label>Email <input type="email" value="<?= e($profile['email']) ?>" disabled></label>
    <label>Phone <input type="text" name="phone" value="<?= e($profile['phone']) ?>"></label>
    <label>Newsletter
      <select name="newsletter">
        <option value="1" <?= (int) $profile['newsletter'] === 1 ? 'selected' : '' ?>>Subscribed</option>
        <option value="0" <?= (int) $profile['newsletter'] === 0 ? 'selected' : '' ?>>Unsubscribed</option>
      </select>
    </label>
    <label>Preferred categories <input type="text" name="preferences[categories]" value="<?= e($prefCats ?? '') ?>" placeholder="Electronics, Books"></label>
    <button class="btn btn-primary" type="submit">Save profile</button>
  </form>
</div>

<div class="card form">
  <h2>Change password</h2>
  <form method="post" action="/account/profile">
    <?= csrf_field($csrf) ?>
    <input type="hidden" name="name" value="<?= e($profile['name']) ?>">
    <label>Current password <input type="password" name="current_password"></label>
    <label>New password <input type="password" name="password" minlength="6"></label>
    <button class="btn" type="submit">Update password</button>
  </form>
</div>
