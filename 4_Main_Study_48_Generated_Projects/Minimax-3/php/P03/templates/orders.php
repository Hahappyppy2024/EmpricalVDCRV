<?php $page_title = $page_title ?? 'Orders'; ?>
<section>
  <h1>Orders</h1>
  <?php if (!$orders): ?>
    <p>No orders yet.</p>
  <?php else: ?>
    <table class="table">
      <thead><tr><th>Reference</th><th>Status</th><th>Total</th><th>Placed</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($orders as $o): ?>
          <tr>
            <td><?= htmlspecialchars($o['reference'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><span class="status status-<?= htmlspecialchars($o['status'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($o['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
            <td>$<?= number_format((int)$o['total_cents'] / 100, 2) ?></td>
            <td><?= htmlspecialchars($o['placed_at'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><a class="btn btn-ghost" href="/orders/<?= (int)$o['id'] ?>">View</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>