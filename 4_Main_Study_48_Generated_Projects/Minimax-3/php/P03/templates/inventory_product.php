<?php $page_title = $page_title ?? 'Inventory product'; ?>
<section>
  <h1>Inventory for <?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?></h1>
  <p>Current quantity: <strong><?= (int)$inventory['quantity'] ?></strong> · Restock threshold: <?= (int)$inventory['restock_threshold'] ?></p>
  <form method="post" action="/inventory/<?= (int)$product['id'] ?>" class="form">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
    <label>Delta (positive to add, negative to remove) <input type="number" name="delta" required></label>
    <label>Reason <input type="text" name="reason" value="manual"></label>
    <label>Restock threshold <input type="number" name="restock_threshold" min="0" value="<?= (int)$inventory['restock_threshold'] ?>"></label>
    <button class="btn btn-primary" type="submit">Apply</button>
  </form>
  <h2>Events</h2>
  <ul>
    <?php foreach ($events as $e): ?>
      <li><?= htmlspecialchars($e['created_at'], ENT_QUOTES, 'UTF-8') ?> · <?= (int)$e['delta'] ?> · <?= htmlspecialchars($e['reason'], ENT_QUOTES, 'UTF-8') ?></li>
    <?php endforeach; ?>
  </ul>
</section>