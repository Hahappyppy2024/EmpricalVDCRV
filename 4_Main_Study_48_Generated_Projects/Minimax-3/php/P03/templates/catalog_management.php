<?php $page_title = $page_title ?? 'Catalog management'; ?>
<section>
  <h1>Catalog management</h1>
  <p><a class="btn btn-ghost" href="/seller/catalog/new">New product</a></p>
  <table class="table">
    <thead><tr><th>SKU</th><th>Name</th><th>Status</th><th>Stock</th><th>Price</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($products as $p): ?>
        <tr>
          <td><?= htmlspecialchars($p['sku'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($p['status'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= (int)$p['stock'] ?></td>
          <td>$<?= number_format((int)$p['price_cents'] / 100, 2) ?></td>
          <td><a class="btn btn-ghost" href="/inventory/<?= (int)$p['id'] ?>">Inventory</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <h2>Create product (API)</h2>
  <form method="post" action="/api/shop/catalog_management" data-ajax="true" class="form">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
    <label>SKU <input type="text" name="sku" required></label>
    <label>Name <input type="text" name="name" required></label>
    <label>Description <textarea name="description"></textarea></label>
    <label>Price (cents) <input type="number" name="price_cents" min="0" required></label>
    <label>Category
      <select name="category_id">
        <option value="">—</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Status
      <select name="status">
        <option value="published">published</option>
        <option value="draft">draft</option>
        <option value="archived">archived</option>
      </select>
    </label>
    <label>Initial stock <input type="number" name="initial_stock" min="0" value="0"></label>
    <button class="btn btn-primary" type="submit">Create</button>
  </form>
</section>