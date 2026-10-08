<?php $pageTitle = 'Manage products'; ?>
<h1>Manage products</h1>
<div class="tabs">
  <a href="/seller">Overview</a>
  <a href="/seller/products">Products</a>
  <a href="/seller/orders">Orders</a>
  <a href="/seller/inventory">Inventory</a>
  <a href="/seller/reports">Reports</a>
  <a href="/seller/promotions">Promotions</a>
</div>

<form method="get" action="/seller/products" class="filters">
  <input type="text" name="keyword" value="<?= e((string) ($filters['keyword'] ?? '')) ?>" placeholder="Search your products">
  <button class="btn" type="submit">Filter</button>
  <a class="btn btn-primary" href="/seller/products/new">New product</a>
</form>

<?php if ($products === []): ?>
  <div class="empty">No products yet. <a href="/seller/products/new">Create one</a>.</div>
<?php else: ?>
  <table class="table">
    <thead>
    <tr><th>Image</th><th>Name</th><th>Category</th><th>Price</th><th>Stock</th><th>Visible</th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($products as $p): ?>
      <tr>
        <td><img src="/uploads/<?= e($p['image']) ?>" alt="" class="thumb"></td>
        <td><?= e($p['name']) ?><?= (int) $p['featured'] === 1 ? ' <span class="badge">featured</span>' : '' ?></td>
        <td><?= e($p['category_name']) ?></td>
        <td><?= money((float) $p['price'], $currency) ?></td>
        <td><?= (int) $p['stock'] ?></td>
        <td><?= (int) $p['active'] === 1 ? 'yes' : 'no' ?></td>
        <td>
          <a class="btn btn-sm" href="/seller/products/<?= (int) $p['id'] ?>/edit">Edit</a>
          <form method="post" action="/seller/products/<?= (int) $p['id'] ?>/toggle" class="inline">
            <?= csrf_field($csrf) ?>
            <button class="btn btn-sm" type="submit"><?= (int) $p['active'] === 1 ? 'Hide' : 'Show' ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
