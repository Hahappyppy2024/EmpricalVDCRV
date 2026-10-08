<?php $pageTitle = 'Inventory'; ?>
<h1>Inventory</h1>
<div class="tabs">
  <a href="/seller">Overview</a>
  <a href="/seller/products">Products</a>
  <a href="/seller/orders">Orders</a>
  <a href="/seller/inventory">Inventory</a>
  <a href="/seller/reports">Reports</a>
  <a href="/seller/promotions">Promotions</a>
</div>

<section class="section">
  <h2>Stock levels</h2>
  <table class="table">
    <thead>
    <tr><th>Product</th><th>Stock</th><th>Restock</th><th>Adjust</th></tr>
    </thead>
    <tbody>
    <?php foreach ($products as $p): ?>
      <tr>
        <td><?= e($p['name']) ?><?= (int) $p['stock'] <= 3 ? ' <span class="badge badge-warn">low</span>' : '' ?></td>
        <td data-stock-cell="<?= (int) $p['id'] ?>"><?= (int) $p['stock'] ?></td>
        <td>
          <form method="post" action="/seller/inventory/<?= (int) $p['id'] ?>/restock" class="inline-form">
            <?= csrf_field($csrf) ?>
            <input type="number" name="quantity" value="10" min="1" class="qty-input">
            <button class="btn btn-sm" type="submit">Restock</button>
          </form>
        </td>
        <td>
          <form method="post" action="/seller/inventory/<?= (int) $p['id'] ?>/adjust" class="inline-form">
            <?= csrf_field($csrf) ?>
            <input type="number" name="delta" value="-1" step="1" class="qty-input">
            <input type="text" name="reason" placeholder="reason" class="small-input">
            <button class="btn btn-sm" type="submit">Adjust</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>

<section class="section">
  <h2>Recent stock movements</h2>
  <?php if ($movements === []): ?>
    <div class="empty">No movements recorded.</div>
  <?php else: ?>
    <table class="table">
      <thead><tr><th>Product</th><th>Delta</th><th>Reason</th><th>User</th><th>At</th></tr></thead>
      <tbody>
      <?php foreach ($movements as $m): ?>
        <tr>
          <td><?= e((string) ($m['product_name'] ?? '—')) ?></td>
          <td><?= (int) $m['delta'] > 0 ? '+' : '' ?><?= (int) $m['delta'] ?></td>
          <td><?= e($m['reason']) ?></td>
          <td><?= e((string) ($m['user_name'] ?? 'system')) ?></td>
          <td><?= e($m['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>
