<?php $page_title = $page_title ?? 'Order'; ?>
<section>
  <h1>Order <?= htmlspecialchars($order['reference'], ENT_QUOTES, 'UTF-8') ?></h1>
  <p>Status: <span class="status status-<?= htmlspecialchars($order['status'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($order['status'], ENT_QUOTES, 'UTF-8') ?></span></p>
  <table class="table">
    <thead><tr><th>Product</th><th>Quantity</th><th>Unit price</th><th>Line total</th></tr></thead>
    <tbody>
      <?php foreach ($items as $it): ?>
        <tr>
          <td><?= htmlspecialchars($it['product_name'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= (int)$it['quantity'] ?></td>
          <td>$<?= number_format((int)$it['unit_price_cents'] / 100, 2) ?></td>
          <td>$<?= number_format((int)$it['line_total_cents'] / 100, 2) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <p>
    Subtotal: $<?= number_format((int)$order['subtotal_cents'] / 100, 2) ?><br>
    Discount: -$<?= number_format((int)$order['discount_cents'] / 100, 2) ?><br>
    <strong>Total: $<?= number_format((int)$order['total_cents'] / 100, 2) ?></strong>
  </p>
  <p>
    <a class="btn btn-ghost" href="/orders/<?= (int)$order['id'] ?>/lifecycle">Lifecycle</a>
    <a class="btn btn-ghost" href="/orders">Back</a>
  </p>
  <h2>History</h2>
  <ul>
    <?php foreach ($events as $e): ?>
      <li><?= htmlspecialchars($e['created_at'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars(($e['from_status'] ?? '—') . ' → ' . $e['to_status'], ENT_QUOTES, 'UTF-8') ?> <?= $e['note'] ? '· ' . htmlspecialchars($e['note'], ENT_QUOTES, 'UTF-8') : '' ?></li>
    <?php endforeach; ?>
  </ul>
</section>