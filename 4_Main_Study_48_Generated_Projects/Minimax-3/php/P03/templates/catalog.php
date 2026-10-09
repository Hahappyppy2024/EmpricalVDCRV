<?php $page_title = $page_title ?? 'Catalog'; ?>
<section>
  <h1>Catalog search</h1>
  <form method="get" action="/catalog" class="form inline-form">
    <label>Search <input type="text" name="q" value="<?= htmlspecialchars($filters['q'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></label>
    <label>Category
      <select name="category">
        <option value="">All</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= htmlspecialchars($c['slug'], ENT_QUOTES, 'UTF-8') ?>" <?= ($filters['category'] ?? '') === $c['slug'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Min price ($) <input type="number" min="0" step="0.01" name="min_price_cents" value="<?= htmlspecialchars((string)($filters['min_price_cents'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label>
    <label>Max price ($) <input type="number" min="0" step="0.01" name="max_price_cents" value="<?= htmlspecialchars((string)($filters['max_price_cents'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label>
    <label class="checkbox"><input type="checkbox" name="in_stock" value="1" <?= !empty($filters['in_stock']) ? 'checked' : '' ?>> In stock only</label>
    <button class="btn btn-primary" type="submit">Filter</button>
  </form>

  <p class="muted"><?= count($results) ?> result(s).</p>
  <div class="grid">
    <?php foreach ($results as $p): ?>
      <a class="card" href="/products/<?= (int)$p['id'] ?>">
        <img class="thumb" src="<?= htmlspecialchars($p['image_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>" alt="">
        <div class="card-body">
          <h3><?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?></h3>
          <p class="muted"><?= htmlspecialchars($p['category_name'] ?? '', ENT_QUOTES, 'UTF-8') ?> · <?= (int)$p['stock'] ?> in stock</p>
          <p class="price">$<?= number_format((int)$p['price_cents'] / 100, 2) ?></p>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
</section>