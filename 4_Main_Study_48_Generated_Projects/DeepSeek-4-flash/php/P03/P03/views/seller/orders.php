<?php $pageTitle = 'Orders'; ?>
<h1>Orders</h1>
<div class="tabs">
  <a href="/seller">Overview</a>
  <a href="/seller/products">Products</a>
  <a href="/seller/orders">Orders</a>
  <a href="/seller/inventory">Inventory</a>
  <a href="/seller/reports">Reports</a>
  <a href="/seller/promotions">Promotions</a>
</div>

<form method="get" action="/seller/orders" class="filters">
  <input type="text" name="number" value="<?= e((string) ($filters['number'] ?? '')) ?>" placeholder="Order number">
  <select name="status">
    <option value="">All statuses</option>
    <?php foreach ($statuses as $s): ?>
      <option value="<?= e($s) ?>" <?= ($filters['status'] ?? '') === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn" type="submit">Filter</button>
</form>

<?php if ($orders === []): ?>
  <div class="empty">No orders match.</div>
<?php else: ?>
  <table class="table">
    <thead>
    <tr><th>Number</th><th>Customer</th><th>Status</th><th>Total</th><th>Placed</th><th>Update status</th></tr>
    </thead>
    <tbody>
    <?php foreach ($orders as $o): ?>
      <tr data-order-row="<?= (int) $o['id'] ?>">
        <td><?= e($o['number']) ?></td>
        <td><?= e($o['customer_name']) ?><br><span class="muted small"><?= e($o['customer_email']) ?></span></td>
        <td><span class="badge" data-order-status="<?= (int) $o['id'] ?>"><?= e($o['status']) ?></span></td>
        <td><?= money((float) $o['total'], $currency) ?></td>
        <td><?= e($o['created_at']) ?></td>
        <td>
          <?php $allowed = \Shop\Service\OrderService::TRANSITIONS[$o['status']] ?? []; ?>
          <?php if ($allowed !== []): ?>
            <form method="post" action="/seller/orders/<?= (int) $o['id'] ?>/status" class="inline-form">
              <?= csrf_field($csrf) ?>
              <select name="status">
                <?php foreach ($allowed as $t): ?>
                  <option value="<?= e($t) ?>"><?= e(ucfirst($t)) ?></option>
                <?php endforeach; ?>
              </select>
              <button class="btn btn-sm" type="submit">Apply</button>
            </form>
          <?php else: ?>
            <span class="muted small">terminal</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
