<?php $pageTitle = 'Admin dashboard'; ?>
<h1>Admin dashboard</h1>
<div class="tabs">
  <a href="/admin">Overview</a>
  <a href="/admin/users">Users</a>
  <a href="/admin/settings">Settings</a>
  <a href="/admin/reviews">Reviews</a>
  <a href="/admin/audit">Audit log</a>
</div>

<div class="stat-grid">
  <div class="stat card"><h3>Orders</h3><p><?= (int) $stats['orders']['total'] ?></p></div>
  <div class="stat card"><h3>Revenue</h3><p><?= money((float) $stats['orders']['revenue'], $currency) ?></p></div>
  <div class="stat card"><h3>Products</h3><p><?= (int) $stats['products']['total'] ?></p></div>
  <div class="stat card"><h3>Low stock</h3><p><?= (int) $stats['products']['low_stock'] ?></p></div>
  <div class="stat card"><h3>Users</h3><p><?= (int) ($stats['users']['total'] ?? 0) ?></p></div>
  <div class="stat card"><h3>Customers</h3><p><?= (int) ($stats['users']['customers'] ?? 0) ?></p></div>
</div>

<div class="card">
  <h2>Order status counts</h2>
  <div class="chips">
    <?php foreach ($stats['orders']['by_status'] as $status => $count): ?>
      <span class="chip"><?= e($status) ?>: <?= (int) $count ?></span>
    <?php endforeach; ?>
  </div>
</div>
