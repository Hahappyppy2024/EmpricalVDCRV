<?php $pageTitle = 'Seller dashboard'; ?>
<h1>Seller dashboard</h1>
<div class="tabs">
  <a href="/seller">Overview</a>
  <a href="/seller/products">Products</a>
  <a href="/seller/orders">Orders</a>
  <a href="/seller/inventory">Inventory</a>
  <a href="/seller/reports">Reports</a>
  <a href="/seller/promotions">Promotions</a>
</div>

<div class="stat-grid">
  <div class="stat card"><h3>Orders</h3><p><?= (int) $stats['orders']['total'] ?></p></div>
  <div class="stat card"><h3>Revenue (paid+)</h3><p><?= money((float) $stats['orders']['revenue'], $currency) ?></p></div>
  <div class="stat card"><h3>Products</h3><p><?= (int) $stats['products']['total'] ?></p></div>
  <div class="stat card"><h3>Low stock</h3><p><?= (int) $stats['products']['low_stock'] ?></p></div>
  <?php if (isset($stats['users'])): ?>
    <div class="stat card"><h3>Users</h3><p><?= (int) $stats['users']['total'] ?></p></div>
    <div class="stat card"><h3>Customers</h3><p><?= (int) $stats['users']['customers'] ?></p></div>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Order status counts</h2>
  <div class="chips">
    <?php foreach ($stats['orders']['by_status'] as $status => $count): ?>
      <span class="chip"><?= e($status) ?>: <?= (int) $count ?></span>
    <?php endforeach; ?>
  </div>
</div>
