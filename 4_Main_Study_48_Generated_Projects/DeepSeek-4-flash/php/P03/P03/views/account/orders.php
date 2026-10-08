<?php $pageTitle = 'My orders'; ?>
<h1>My orders</h1>
<div class="tabs">
  <a href="/account">Profile</a>
  <a href="/account/addresses">Addresses</a>
  <a href="/account/orders">Orders</a>
</div>

<form method="get" action="/account/orders" class="filters">
  <input type="text" name="number" value="<?= e((string) ($filters['number'] ?? '')) ?>" placeholder="Order number">
  <select name="status">
    <option value="">All statuses</option>
    <?php foreach (\Shop\Service\OrderService::STATUSES as $s): ?>
      <option value="<?= e($s) ?>" <?= ($filters['status'] ?? '') === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn" type="submit">Filter</button>
</form>

<?php if ($orders === []): ?>
  <div class="empty">No orders found.</div>
<?php else: ?>
  <table class="table" data-orders-list>
    <thead>
    <tr><th>Number</th><th>Status</th><th>Total</th><th>Placed</th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($orders as $o): ?>
      <tr data-order-row="<?= (int) $o['id'] ?>">
        <td><?= e($o['number']) ?></td>
        <td><span class="badge" data-order-status="<?= (int) $o['id'] ?>"><?= e($o['status']) ?></span></td>
        <td><?= money((float) $o['total'], $currency) ?></td>
        <td><?= e($o['created_at']) ?></td>
        <td><a class="btn btn-sm" href="/account/orders/<?= (int) $o['id'] ?>">View</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
