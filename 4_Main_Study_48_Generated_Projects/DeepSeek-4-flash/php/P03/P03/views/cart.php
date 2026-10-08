<?php $pageTitle = 'Shopping cart'; ?>
<h1>Shopping cart</h1>

<?php if ($items === []): ?>
  <div class="empty">Your cart is empty. <a href="/catalog">Browse the catalog</a>.</div>
<?php else: ?>
  <table class="table">
    <thead>
    <tr><th>Product</th><th>Price</th><th>Quantity</th><th>Line total</th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($items as $item): ?>
      <tr data-cart-row>
        <td>
          <a href="/products/<?= (int) $item['id'] ?>/<?= e($item['slug']) ?>"><?= e($item['name']) ?></a>
          <?php if ((int) $item['stock'] < (int) $item['quantity']): ?>
            <span class="badge badge-warn">only <?= (int) $item['stock'] ?> in stock</span>
          <?php endif; ?>
        </td>
        <td><?= money((float) $item['price'], $currency) ?></td>
        <td>
          <form method="post" action="/cart/update/<?= (int) $item['cart_id'] ?>" class="inline-form">
            <?= csrf_field($csrf) ?>
            <input type="number" name="quantity" value="<?= (int) $item['quantity'] ?>" min="1" max="<?= (int) $item['stock'] ?>" class="qty-input">
            <button class="btn btn-sm" type="submit">Update</button>
          </form>
        </td>
        <td><?= money((float) $item['price'] * (int) $item['quantity'], $currency) ?></td>
        <td>
          <form method="post" action="/cart/remove/<?= (int) $item['cart_id'] ?>">
            <?= csrf_field($csrf) ?>
            <button class="btn btn-sm btn-danger" type="submit">Remove</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <div class="totals card">
    <div><span>Subtotal</span><span><?= money((float) $totals['subtotal'], $currency) ?></span></div>
    <div><span>Shipping</span><span><?= money((float) $totals['shipping'], $currency) ?></span></div>
    <div><span>Tax</span><span><?= money((float) $totals['tax'], $currency) ?></span></div>
    <div class="grand"><span>Total</span><span><?= money((float) $totals['total'], $currency) ?></span></div>
    <a class="btn btn-primary" href="/checkout">Proceed to checkout</a>
  </div>
<?php endif; ?>
