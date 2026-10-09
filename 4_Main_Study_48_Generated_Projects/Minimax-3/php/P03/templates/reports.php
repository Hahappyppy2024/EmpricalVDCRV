<?php $page_title = $page_title ?? 'Reports'; ?>
<section>
  <h1>Reports</h1>
  <div class="grid">
    <div>
      <h2>Sales</h2>
      <p><a class="btn btn-ghost" href="/reports/export.csv?type=sales">Download CSV</a></p>
      <table class="table">
        <thead><tr><th>Reference</th><th>Status</th><th>Customer</th><th>Total</th><th>Placed</th></tr></thead>
        <tbody>
          <?php foreach ($sales as $s): ?>
            <tr>
              <td><?= htmlspecialchars($s['reference'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars($s['status'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars($s['customer_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
              <td>$<?= number_format((int)$s['total_cents'] / 100, 2) ?></td>
              <td><?= htmlspecialchars($s['placed_at'], ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div>
      <h2>Inventory</h2>
      <p><a class="btn btn-ghost" href="/reports/export.csv?type=inventory">Download CSV</a></p>
      <table class="table">
        <thead><tr><th>SKU</th><th>Name</th><th>Qty</th><th>Threshold</th><th>Needs restock</th></tr></thead>
        <tbody>
          <?php foreach ($inventory_report as $i): ?>
            <tr class="<?= ((int)$i['needs_restock']) === 1 ? 'low-stock' : '' ?>">
              <td><?= htmlspecialchars($i['sku'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars($i['name'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= (int)$i['quantity'] ?></td>
              <td><?= (int)$i['restock_threshold'] ?></td>
              <td><?= ((int)$i['needs_restock']) === 1 ? 'YES' : 'OK' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div>
      <h2>Customers</h2>
      <p><a class="btn btn-ghost" href="/reports/export.csv?type=customers">Download CSV</a></p>
      <table class="table">
        <thead><tr><th>Name</th><th>Email</th><th>Orders</th><th>Lifetime value</th></tr></thead>
        <tbody>
          <?php foreach ($customers as $c): ?>
            <tr>
              <td><?= htmlspecialchars($c['display_name'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars($c['email'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= (int)$c['order_count'] ?></td>
              <td>$<?= number_format((int)$c['lifetime_value_cents'] / 100, 2) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>