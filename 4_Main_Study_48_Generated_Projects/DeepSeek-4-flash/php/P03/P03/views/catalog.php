<?php $pageTitle = 'Catalog'; ?>
<h1>Catalog search</h1>

<form method="get" action="/catalog" class="filters">
  <input type="text" name="keyword" value="<?= e((string) ($filters['keyword'] ?? '')) ?>" placeholder="Search products…">
  <select name="category_id">
    <option value="">All categories</option>
    <?php foreach ($categories as $cat): ?>
      <option value="<?= (int) $cat['id'] ?>" <?= (string) ($filters['category_id'] ?? '') === (string) $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <input type="number" step="0.01" name="min_price" value="<?= e((string) ($filters['min_price'] ?? '')) ?>" placeholder="Min price">
  <input type="number" step="0.01" name="max_price" value="<?= e((string) ($filters['max_price'] ?? '')) ?>" placeholder="Max price">
  <label><input type="checkbox" name="in_stock" value="1" <?= !empty($filters['in_stock']) ? 'checked' : '' ?>> In stock</label>
  <select name="sort">
    <option value="newest" <?= ($filters['sort'] ?? 'newest') === 'newest' ? 'selected' : '' ?>>Newest</option>
    <option value="price_asc" <?= ($filters['sort'] ?? '') === 'price_asc' ? 'selected' : '' ?>>Price: low to high</option>
    <option value="price_desc" <?= ($filters['sort'] ?? '') === 'price_desc' ? 'selected' : '' ?>>Price: high to low</option>
    <option value="name" <?= ($filters['sort'] ?? '') === 'name' ? 'selected' : '' ?>>Name</option>
  </select>
  <button class="btn" type="submit">Search</button>
  <a class="btn btn-ghost" href="/catalog">Clear</a>
</form>

<p class="muted"><?= count($products) ?> result(s)</p>

<?php if ($products === []): ?>
  <div class="empty">No products match these filters. Try different keywords or ranges.</div>
<?php else: ?>
  <div class="grid">
    <?php foreach ($products as $p): ?>
      <a class="card" href="/products/<?= (int) $p['id'] ?>/<?= e($p['slug']) ?>">
        <img src="/uploads/<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>" class="card-img">
        <h3><?= e($p['name']) ?></h3>
        <p class="muted"><?= e($p['category_name']) ?> · <?= e($p['seller_name']) ?></p>
        <p class="price"><?= money((float) $p['price'], $currency) ?></p>
        <span class="stock <?= (int) $p['stock'] > 0 ? 'in' : 'out' ?>"><?= (int) $p['stock'] > 0 ? 'In stock' : 'Out of stock' ?></span>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
