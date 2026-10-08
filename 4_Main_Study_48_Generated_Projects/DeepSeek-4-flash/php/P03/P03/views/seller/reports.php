<?php $pageTitle = 'Reports'; ?>
<h1>Reports</h1>
<div class="tabs">
  <a href="/seller">Overview</a>
  <a href="/seller/products">Products</a>
  <a href="/seller/orders">Orders</a>
  <a href="/seller/inventory">Inventory</a>
  <a href="/seller/reports">Reports</a>
  <a href="/seller/promotions">Promotions</a>
</div>

<div class="chips">
  <?php foreach ($types as $t): ?>
    <a class="chip <?= $type === $t ? 'active' : '' ?>" href="/seller/reports?type=<?= e($t) ?>"><?= e(ucfirst($t)) ?></a>
  <?php endforeach; ?>
  <a class="chip" href="/seller/reports/export/<?= e($type) ?>">⬇ Download <?= e($type) ?> CSV</a>
</div>

<?php if ($type === 'sales'): ?>
  <div class="stat-grid">
    <div class="stat card"><h3>Orders</h3><p><?= (int) $report['totals']['orders'] ?></p></div>
    <div class="stat card"><h3>Revenue (all)</h3><p><?= money((float) $report['totals']['revenue'], $currency) ?></p></div>
    <div class="stat card"><h3>Delivered</h3><p><?= money((float) $report['totals']['delivered'], $currency) ?></p></div>
    <div class="stat card"><h3>Refunded</h3><p><?= money((float) $report['totals']['refunded'], $currency) ?></p></div>
  </div>
  <div class="card">
    <h2>Top products</h2>
    <table class="table">
      <thead><tr><th>Product</th><th>Units sold</th><th>Revenue</th></tr></thead>
      <tbody>
      <?php foreach ($report['top_products'] as $t): ?>
        <tr><td><?= e($t['product_name']) ?></td><td><?= (int) $t['sold'] ?></td><td><?= money((float) $t['revenue'], $currency) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php elseif ($type === 'inventory'): ?>
  <div class="card">
    <h2>Stock value</h2>
    <table class="table">
      <thead><tr><th>Product</th><th>Stock</th><th>Unit price</th><th>Value</th></tr></thead>
      <tbody>
      <?php foreach ($report['stock_value'] as $p): ?>
        <tr><td><?= e($p['name']) ?></td><td><?= (int) $p['stock'] ?></td><td><?= money((float) $p['price'], $currency) ?></td><td><?= money((float) $p['value'], $currency) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php else: ?>
  <div class="card">
    <h2>Customer report</h2>
    <table class="table">
      <thead><tr><th>Name</th><th>Email</th><th>Orders</th><th>Spend</th><th>Active</th></tr></thead>
      <tbody>
      <?php foreach ($report['customers'] as $c): ?>
        <tr>
          <td><?= e($c['name']) ?></td>
          <td><?= e($c['email']) ?></td>
          <td><?= (int) $c['orders'] ?></td>
          <td><?= money((float) $c['spend'], $currency) ?></td>
          <td><?= (int) $c['active'] === 1 ? 'yes' : 'no' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
