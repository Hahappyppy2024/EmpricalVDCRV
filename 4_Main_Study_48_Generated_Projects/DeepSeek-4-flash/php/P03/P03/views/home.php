<?php $pageTitle = 'Home'; ?>
<section class="hero">
  <h1>Welcome to <?= e($appName ?? 'the store') ?></h1>
  <p>Browse a deterministic product catalog, add items to your cart and place simulated orders.</p>
  <a class="btn btn-primary" href="/catalog">Browse catalog</a>
</section>

<section class="section">
  <h2>Categories</h2>
  <div class="chips">
    <?php foreach ($categories as $cat): ?>
      <a class="chip" href="/catalog?category_id=<?= (int) $cat['id'] ?>"><?= e($cat['name']) ?></a>
    <?php endforeach; ?>
  </div>
</section>

<section class="section">
  <h2>Featured products</h2>
  <?php if ($products === []): ?>
    <p class="muted">No products available yet.</p>
  <?php else: ?>
    <div class="grid">
      <?php foreach ($products as $p): ?>
        <a class="card" href="/products/<?= (int) $p['id'] ?>/<?= e($p['slug']) ?>">
          <img src="/uploads/<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>" class="card-img">
          <h3><?= e($p['name']) ?></h3>
          <p class="muted"><?= e($p['category_name']) ?></p>
          <p class="price"><?= money((float) $p['price'], $currency) ?></p>
          <span class="stock <?= (int) $p['stock'] > 0 ? 'in' : 'out' ?>"><?= (int) $p['stock'] > 0 ? 'In stock' : 'Out of stock' ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
