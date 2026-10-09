<?php $page_title = $page_title ?? 'Checkout'; ?>
<section>
  <h1>Checkout</h1>
  <div class="grid two">
    <div>
      <h2>Shipping address</h2>
      <form method="post" action="/checkout" class="form">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <label>Full name <input type="text" name="full_name" required></label>
        <label>Address line 1 <input type="text" name="line1" required></label>
        <label>Address line 2 <input type="text" name="line2"></label>
        <label>City <input type="text" name="city" required></label>
        <label>Region <input type="text" name="region"></label>
        <label>Postal code <input type="text" name="postal_code" required></label>
        <label>Country <input type="text" name="country" value="US"></label>
        <fieldset>
          <legend>Payment</legend>
          <label>Method
            <select name="payment_method">
              <option value="card">Card</option>
              <option value="paypal">PayPal</option>
            </select>
          </label>
          <label>Fixture token <input type="text" name="payment_token" value="tok_ok" placeholder="tok_ok / tok_decline"></label>
        </fieldset>
        <button class="btn btn-primary" type="submit">Place order ($<?= number_format((int)$cart['total_cents'] / 100, 2) ?>)</button>
      </form>
    </div>
    <div>
      <h2>Order summary</h2>
      <ul class="cart-summary">
        <?php foreach ($cart['items'] as $it): ?>
            <li><?= htmlspecialchars($it['name'], ENT_QUOTES, 'UTF-8') ?> × <?= (int)$it['quantity'] ?> — $<?= number_format((int)$it['line_total'] / 100, 2) ?></li>
        <?php endforeach; ?>
      </ul>
      <p>
        Subtotal: $<?= number_format((int)$cart['subtotal_cents'] / 100, 2) ?><br>
        Discount: -$<?= number_format((int)$cart['discount_cents'] / 100, 2) ?><br>
        <strong>Total: $<?= number_format((int)$cart['total_cents'] / 100, 2) ?></strong>
      </p>
      <?php if ($addresses): ?>
        <h3>Saved addresses</h3>
        <ul>
          <?php foreach ($addresses as $a): ?>
            <li><?= htmlspecialchars($a['full_name'] . ' — ' . $a['line1'] . ', ' . $a['city'], ENT_QUOTES, 'UTF-8') ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
</section>