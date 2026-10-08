<?php $pageTitle = 'Order ' . $order['number']; ?>
<h1>Order <?= e($order['number']) ?></h1>

<div class="card">
  <h2>Status: <span class="badge badge-lg" data-order-status="<?= (int) $order['id'] ?>"><?= e($order['status']) ?></span></h2>
  <p class="muted">Placed <?= e($order['created_at']) ?> · Payment <?= e($order['payment_method']) ?>
    <?= $order['payment_reference'] !== '' ? '· ref ' . e($order['payment_reference']) : '' ?></p>

  <table class="table">
    <thead><tr><th>Product</th><th>Price</th><th>Qty</th><th>Line total</th></tr></thead>
    <tbody>
    <?php foreach ($order['items'] as $item): ?>
      <tr>
        <td><?= e($item['product_name']) ?></td>
        <td><?= money((float) $item['unit_price'], $currency) ?></td>
        <td><?= (int) $item['quantity'] ?></td>
        <td><?= money((float) $item['line_total'], $currency) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <div class="totals">
    <div><span>Subtotal</span><span><?= money((float) $order['subtotal'], $currency) ?></span></div>
    <div><span>Shipping</span><span><?= money((float) $order['shipping'], $currency) ?></span></div>
    <div><span>Tax</span><span><?= money((float) $order['tax'], $currency) ?></span></div>
    <?php if ((float) $order['discount'] > 0): ?>
      <div><span>Discount</span><span>-<?= money((float) $order['discount'], $currency) ?></span></div>
    <?php endif; ?>
    <div class="grand"><span>Total</span><span><?= money((float) $order['total'], $currency) ?></span></div>
  </div>
</div>

<?php if ($order['allowed_transitions'] !== []): ?>
  <div class="card form">
    <h2>Update status</h2>
    <form method="post" action="/account/orders/<?= (int) $order['id'] ?>/status">
      <?= csrf_field($csrf) ?>
      <label>Move to
        <select name="status">
          <?php foreach ($order['allowed_transitions'] as $t): ?>
            <option value="<?= e($t) ?>"><?= e(ucfirst($t)) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Comment <input type="text" name="comment"></label>
      <button class="btn btn-primary" type="submit">Apply</button>
    </form>
  </div>
<?php endif; ?>

<div class="card">
  <h2>Status history</h2>
  <table class="table">
    <thead><tr><th>From</th><th>To</th><th>By</th><th>Comment</th><th>At</th></tr></thead>
    <tbody>
    <?php foreach ($order['history'] as $h): ?>
      <tr>
        <td><?= e((string) ($h['from_status'] ?? '—')) ?></td>
        <td><?= e($h['to_status']) ?></td>
        <td><?= e((string) ($h['changed_by_name'] ?? 'system')) ?></td>
        <td><?= e($h['comment']) ?></td>
        <td><?= e($h['created_at']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
