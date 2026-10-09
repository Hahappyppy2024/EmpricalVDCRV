<?php $page_title = $page_title ?? 'Inventory'; ?>
<section>
  <h1>Inventory</h1>
  <table class="table">
    <thead><tr><th>SKU</th><th>Name</th><th>Quantity</th><th>Threshold</th><th>Needs restock?</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr class="<?= ((int)$r['needs_restock']) === 1 ? 'low-stock' : '' ?>">
          <td><?= htmlspecialchars($r['sku'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= (int)$r['quantity'] ?></td>
          <td><?= (int)$r['restock_threshold'] ?></td>
          <td><?= ((int)$r['needs_restock']) === 1 ? 'YES' : 'OK' ?></td>
          <td><a class="btn btn-ghost" href="/inventory/<?= (int)$r['id'] ?>">Adjust</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>