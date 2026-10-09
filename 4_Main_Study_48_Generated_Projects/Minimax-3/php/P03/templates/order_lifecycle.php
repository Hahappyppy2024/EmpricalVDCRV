<?php $page_title = $page_title ?? 'Order lifecycle'; ?>
<section>
  <h1>Lifecycle for order <?= htmlspecialchars($order['reference'], ENT_QUOTES, 'UTF-8') ?></h1>
  <p>Current status: <span class="status status-<?= htmlspecialchars($order['status'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($order['status'], ENT_QUOTES, 'UTF-8') ?></span></p>
  <form method="post" action="/orders/<?= (int)$order['id'] ?>/lifecycle" class="form">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
    <label>New status
      <select name="status">
        <?php
        $targets = match ($role) {
            'admin' => ['paid', 'shipped', 'delivered', 'cancelled', 'refunded'],
            'seller' => ['shipped', 'delivered'],
            default => ['cancelled'],
        };
        foreach ($targets as $t): ?>
          <option value="<?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Note <textarea name="note"></textarea></label>
    <button class="btn btn-primary" type="submit">Apply</button>
  </form>

  <h2>History</h2>
  <ul>
    <?php foreach ($events as $e): ?>
      <li><?= htmlspecialchars($e['created_at'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars(($e['from_status'] ?? '—') . ' → ' . $e['to_status'], ENT_QUOTES, 'UTF-8') ?> <?= $e['note'] ? '· ' . htmlspecialchars($e['note'], ENT_QUOTES, 'UTF-8') : '' ?></li>
    <?php endforeach; ?>
  </ul>
</section>