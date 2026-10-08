<?php $pageTitle = $product['name']; ?>
<nav class="crumbs"><a href="/catalog">Catalog</a> › <?= e($product['category_name']) ?></nav>

<div class="product-layout">
  <div>
    <img src="/uploads/<?= e($product['image']) ?>" alt="<?= e($product['name']) ?>" class="product-img">
  </div>
  <div>
    <h1><?= e($product['name']) ?></h1>
    <p class="muted">Sold by <?= e($product['seller_name']) ?></p>
    <p class="price price-lg"><?= money((float) $product['price'], $currency) ?></p>
    <p>
      <span class="stock <?= (int) $product['stock'] > 0 ? 'in' : 'out' ?>">
        <?= (int) $product['stock'] > 0 ? 'In stock (' . (int) $product['stock'] . ' available)' : 'Out of stock' ?>
      </span>
    </p>
    <p><?= e($product['description']) ?></p>

    <?php if (!empty($user)): ?>
      <form method="post" action="/cart/add" class="inline-form">
        <?= csrf_field($csrf) ?>
        <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
        <input type="hidden" name="return_to" value="/products/<?= (int) $product['id'] ?>">
        <input type="number" name="quantity" value="1" min="1" max="<?= (int) $product['stock'] ?>" class="qty-input">
        <button class="btn btn-primary" type="submit" <?= (int) $product['stock'] < 1 ? 'disabled' : '' ?>>Add to cart</button>
      </form>
    <?php else: ?>
      <p><a class="btn btn-primary" href="/login?error=Please%20sign%20in%20to%20add%20items%20to%20your%20cart.">Sign in to shop</a></p>
    <?php endif; ?>
  </div>
</div>

<section class="section">
  <h2>Reviews (<?= (int) $reviewSummary['count'] ?>) — average <?= number_format((float) $reviewSummary['average'], 1) ?> / 5</h2>

  <?php if (!empty($user) && $user['role'] === 'customer'): ?>
    <form method="post" action="/reviews" class="card form" data-jsreview>
      <?= csrf_field($csrf) ?>
      <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
      <label>Rating (1–5)
        <select name="rating" required>
          <option value="5">5 — Excellent</option>
          <option value="4">4 — Good</option>
          <option value="3">3 — Average</option>
          <option value="2">2 — Poor</option>
          <option value="1">1 — Terrible</option>
        </select>
      </label>
      <label>Title <input type="text" name="title" maxlength="120"></label>
      <label>Review text <textarea name="text" rows="3" required maxlength="2000"></textarea></label>
      <button class="btn btn-primary" type="submit">Submit review</button>
      <p class="muted small">Reviews are published after moderator approval.</p>
    </form>
  <?php endif; ?>

  <?php if ($reviews === []): ?>
    <div class="empty">No approved reviews yet.</div>
  <?php else: ?>
    <?php foreach ($reviews as $r): ?>
      <div class="review card">
        <div><strong><?= e($r['customer_name']) ?></strong>
          <span class="stars"><?= str_repeat('★', (int) $r['rating']) . str_repeat('☆', 5 - (int) $r['rating']) ?></span>
        </div>
        <?php if ($r['title'] !== ''): ?><h4><?= e($r['title']) ?></h4><?php endif; ?>
        <p><?= e($r['text']) ?></p>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</section>
