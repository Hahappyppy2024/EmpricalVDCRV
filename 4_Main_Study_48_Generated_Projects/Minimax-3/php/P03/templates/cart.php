<?php $page_title = $page_title ?? 'Cart'; ?>
<section>
  <h1>Shopping cart</h1>
  <?php if (!$cart['items']): ?>
    <p>Your cart is empty. <a href="/catalog">Browse the catalog</a>.</p>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr><th>Product</th><th>Quantity</th><th>Unit price</th><th>Line total</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($cart['items'] as $item): ?>
          <tr>
            <td><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td>
              <form method="post" action="/cart/items/<?= (int)$item['id'] ?>" class="inline">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="number" name="quantity" min="0" value="<?= (int)$item['quantity'] ?>">
                <button class="btn btn-ghost" type="submit">Update</button>
              </form>
            </td>
            <td>$<?= number_format((int)$item['unit_price_cents'] / 100, 2) ?></td>
            <td>$<?= number_format((int)$item['line_total'] / 100, 2) ?></td>
            <td>
              <form method="post" action="/cart/items/<?= (int)$item['id'] ?>/delete" class="inline">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <button class="btn btn-danger" type="submit">Remove</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <form method="post" action="/cart/promotion" class="inline-form">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
      <label>Promotion code <input type="text" name="code" value="<?= htmlspecialchars($cart['promotion']['code'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></label>
      <button class="btn btn-ghost" type="submit">Apply</button>
    </form>

    <p class="totals">
      Subtotal: $<?= number_format((int)$cart['subtotal_cents'] / 100, 2) ?><br>
      Discount: -$<?= number_format((int)$cart['discount_cents'] / 100, 2) ?><br>
      <strong>Total: $<?= number_format((int)$cart['total_cents'] / 100, 2) ?></strong>
    </p>

    <p><a class="btn btn-primary" href="/checkout">Proceed to checkout</a></p>
  <?php endif; ?>
</section>