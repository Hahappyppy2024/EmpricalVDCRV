<?php /** @var string $content */ ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e((string) ($csrf ?? '')) ?>">
<title><?= e($pageTitle ?? ($appName ?? 'P03 E-commerce System')) ?></title>
<link rel="stylesheet" href="/css/style.css">
</head>
<body>
<header class="topbar">
  <div class="topbar-inner">
    <a class="brand" href="/"><?= e($appName ?? 'P03 Shop') ?></a>
    <nav class="nav">
      <a href="/catalog">Catalog</a>
      <a href="/cart">Cart</a>
      <?php if (!empty($user)): ?>
        <?php if ($user['role'] === 'customer'): ?>
          <a href="/account/orders">My orders</a>
          <a href="/account">Account</a>
        <?php endif; ?>
        <?php if (in_array($user['role'], ['seller', 'admin'], true)): ?>
          <a href="/seller">Seller</a>
        <?php endif; ?>
        <?php if (in_array($user['role'], ['moderator', 'admin'], true)): ?>
          <a href="/admin/reviews">Moderation</a>
        <?php endif; ?>
        <?php if ($user['role'] === 'admin'): ?>
          <a href="/admin">Admin</a>
        <?php endif; ?>
        <span class="who"><?= e($user['name']) ?> · <?= e($user['role']) ?></span>
        <form method="post" action="/logout" class="inline"><?= csrf_field($csrf) ?>
          <button type="submit" class="link-btn">Sign out</button>
        </form>
      <?php else: ?>
        <a href="/login">Sign in</a>
        <a class="btn btn-primary btn-sm" href="/register">Register</a>
      <?php endif; ?>
    </nav>
  </div>
</header>

<main class="container">
  <?php if (isset($_GET['ok'])): ?>
    <div class="flash flash-ok"><?= e((string) $_GET['ok']) ?></div>
  <?php endif; ?>
  <?php if (isset($_GET['error'])): ?>
    <div class="flash flash-error"><?= e((string) $_GET['error']) ?></div>
  <?php endif; ?>
  <?= $content ?>
</main>

<footer class="footer">
  <?= e($appName ?? 'P03 E-commerce System') ?> · synthetic benchmark · PHP 8.3 / Slim 4 / SQLite
</footer>
<script>window.SHOP_WS_URL = <?= json_encode((string) ($wsUrl ?? 'ws://127.0.0.1:8081')) ?>;</script>
<script src="/js/app.js"></script>
</body>
</html>
