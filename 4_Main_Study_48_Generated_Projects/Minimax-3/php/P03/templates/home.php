<?php $page_title = $page_title ?? 'Home'; ?>
<section class="hero">
  <h1>Welcome to the P03 E-commerce System</h1>
  <p>Browse the catalog, manage your account, and place orders through the full checkout flow.</p>
  <p><a class="btn btn-primary" href="/catalog">Browse catalog</a></p>
</section>
<section>
  <h2>Featured products</h2>
  <div class="grid">
    <?php foreach ($products as $p): ?>
      <a class="card" href="/products/<?= (int)$p['id'] ?>">
        <img class="thumb" src="<?= htmlspecialchars($p['image_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>" alt="">
        <div class="card-body">
          <h3><?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?></h3>
          <p class="muted"><?= htmlspecialchars($p['sku'], ENT_QUOTES, 'UTF-8') ?></p>
          <p class="price">$<?= number_format((int)$p['price_cents'] / 100, 2) ?></p>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<section>
  <h2>Categories</h2>
  <ul class="chip-list">
    <?php foreach ($categories as $c): ?>
      <li><a href="/catalog?category=<?= urlencode((string)$c['slug']) ?>"><?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?></a></li>
    <?php endforeach; ?>
  </ul>
</section>