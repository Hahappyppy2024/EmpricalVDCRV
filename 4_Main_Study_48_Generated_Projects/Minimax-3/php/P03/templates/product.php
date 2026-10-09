<?php $page_title = $page_title ?? 'Product'; ?>
<section>
  <h1><?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?></h1>
  <p class="muted">SKU: <?= htmlspecialchars($product['sku'], ENT_QUOTES, 'UTF-8') ?> · Category: <?= htmlspecialchars($category_name, ENT_QUOTES, 'UTF-8') ?></p>
  <div class="product-detail">
    <img class="thumb large" src="<?= htmlspecialchars($product['image_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>" alt="">
    <div>
      <p class="price large">$<?= number_format((int)$product['price_cents'] / 100, 2) ?></p>
      <p><?= nl2br(htmlspecialchars($product['description'], ENT_QUOTES, 'UTF-8')) ?></p>
      <?php if ($user): ?>
        <form method="post" action="/api/shop/shopping_cart" data-ajax="true">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
          <label>Quantity <input type="number" min="1" value="1" name="quantity"></label>
          <button class="btn btn-primary" type="submit">Add to cart</button>
        </form>
      <?php else: ?>
        <p><a href="/login">Sign in</a> to purchase.</p>
      <?php endif; ?>
    </div>
  </div>

  <section>
    <h2>Reviews</h2>
    <?php if ($user && in_array($user['role'], ['customer', 'admin', 'seller'], true)): ?>
      <form method="post" action="/api/shop/reviews" data-ajax="true" class="form">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
        <label>Rating
          <select name="rating">
            <option value="5">★★★★★</option>
            <option value="4">★★★★</option>
            <option value="3">★★★</option>
            <option value="2">★★</option>
            <option value="1">★</option>
          </select>
        </label>
        <label>Title <input type="text" name="title"></label>
        <label>Review <textarea name="body" required></textarea></label>
        <button class="btn btn-primary" type="submit">Submit review</button>
      </form>
    <?php endif; ?>
    <ul class="reviews">
      <?php foreach ($reviews as $r): ?>
        <li>
          <p class="muted"><?= str_repeat('★', (int)$r['rating']) ?><?= str_repeat('☆', 5 - (int)$r['rating']) ?> · <?= htmlspecialchars($r['display_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
          <?php if (!empty($r['title'])): ?><strong><?= htmlspecialchars($r['title'], ENT_QUOTES, 'UTF-8') ?></strong><?php endif; ?>
          <p><?= nl2br(htmlspecialchars($r['body'], ENT_QUOTES, 'UTF-8')) ?></p>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
</section>