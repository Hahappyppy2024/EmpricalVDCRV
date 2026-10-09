<?php
/** @var string|null $page_title */
$appName = $app_name ?? 'P03 E-commerce';
$cartCount = (int)($cart_count ?? 0);
$user = $user ?? null;
$csrf = $csrf ?? '';
$flash = $flash_html ?? '';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars(($page_title ?? 'P03') . ' · ' . $appName, ENT_QUOTES, 'UTF-8') ?></title>
<link rel="stylesheet" href="/css/styles.css">
</head>
<body>
<header class="topbar">
  <div class="wrap">
    <a class="brand" href="/"><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></a>
    <nav class="main-nav">
      <a href="/catalog">Catalog</a>
      <a href="/orders">Orders</a>
      <?php if ($user): ?>
        <a href="/cart">Cart (<?= $cartCount ?>)</a>
      <?php endif; ?>
      <?php if ($user && in_array($user['role'], ['seller','admin'], true)): ?>
        <a href="/seller/catalog">Catalog mgmt</a>
        <a href="/inventory">Inventory</a>
        <a href="/reports">Reports</a>
      <?php endif; ?>
      <?php if ($user && in_array($user['role'], ['moderator','admin'], true)): ?>
        <a href="/reviews/moderation">Moderation</a>
      <?php endif; ?>
      <?php if ($user && in_array($user['role'], ['customer','seller','admin'], true)): ?>
        <a href="/customer/data">My data</a>
      <?php endif; ?>
      <?php if ($user && $user['role'] === 'admin'): ?>
        <a href="/admin/operations">Admin</a>
      <?php endif; ?>
      <a href="/frontend/api">Frontend API</a>
    </nav>
    <div class="session">
      <?php if ($user): ?>
        <span class="who"><?= htmlspecialchars($user['display_name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8') ?>)</span>
        <form method="post" action="/logout" class="inline">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
          <button class="btn btn-ghost" type="submit">Sign out</button>
        </form>
      <?php else: ?>
        <a class="btn btn-ghost" href="/login">Sign in</a>
        <a class="btn btn-primary" href="/register">Register</a>
      <?php endif; ?>
    </div>
  </div>
</header>
<main class="wrap">
  <?= $flash ?>
  <?= $inner ?? '' ?>
</main>
<footer class="footer">
  <div class="wrap">P03 E-commerce System · synthetic benchmark aligned with PrestaShop 8.1.0</div>
</footer>
<script src="/js/app.js"></script>
</body>
</html>