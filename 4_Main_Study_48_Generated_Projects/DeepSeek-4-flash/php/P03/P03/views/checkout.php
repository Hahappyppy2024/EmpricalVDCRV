<?php $pageTitle = 'Checkout'; ?>
<h1>Checkout</h1>

<form method="post" action="/checkout" class="checkout-layout" data-jscheckout>
  <?= csrf_field($csrf) ?>

  <div class="card form">
    <h2>Shipping address</h2>
    <?php if ($addresses !== []): ?>
      <label>Saved address
        <select name="address_id">
          <?php foreach ($addresses as $a): ?>
            <option value="<?= (int) $a['id'] ?>"><?= e($a['label']) ?> — <?= e($a['line1']) ?>, <?= e($a['city']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <p class="muted small">…or enter a new address below.</p>
    <?php endif; ?>
    <label>Address line 1 <input type="text" name="line1" value="<?= e((string) ($addresses[0]['line1'] ?? '')) ?>"></label>
    <label>Address line 2 <input type="text" name="line2"></label>
    <label>City <input type="text" name="city" value="<?= e((string) ($addresses[0]['city'] ?? '')) ?>"></label>
    <label>Postal code <input type="text" name="postal_code" value="<?= e((string) ($addresses[0]['postal_code'] ?? '')) ?>"></label>
    <label>Country <input type="text" name="country" value="US"></label>
    <label>Phone <input type="text" name="phone"></label>
  </div>

  <div class="card form">
    <h2>Payment (simulated)</h2>
    <p class="muted small">Card numbers ending in <code>0002</code> are declined by the simulator; all others are approved.</p>
    <label>Card number <input type="text" name="card_number" inputmode="numeric" autocomplete="cc-number" placeholder="4111 1111 1111 1111" data-js-card></label>
    <label>Name on card <input type="text" name="card_name"></label>
    <div class="row2">
      <label>Expiry <input type="text" name="card_expiry" placeholder="12/29"></label>
      <label>CVV <input type="text" name="card_cvv" maxlength="4" placeholder="123"></label>
    </div>
    <label>Promotion code <input type="text" name="promotion_code" placeholder="WELCOME10"></label>

    <div class="totals">
      <div><span>Subtotal</span><span><?= money((float) $totals['subtotal'], $currency) ?></span></div>
      <div><span>Shipping</span><span><?= money((float) $totals['shipping'], $currency) ?></span></div>
      <div><span>Tax</span><span><?= money((float) $totals['tax'], $currency) ?></span></div>
      <div class="grand"><span>Total</span><span><?= money((float) $totals['total'], $currency) ?></span></div>
    </div>

    <div class="payment-step" data-jsstatus hidden>
      <h3>Processing payment</h3>
      <ol>
        <li data-pstep="validate">Validate inputs</li>
        <li data-pstep="processing">Authorizing payment</li>
        <li data-pstep="approved">Order confirmed</li>
      </ol>
    </div>

    <button class="btn btn-primary btn-block" type="submit">Place order and pay</button>
  </div>
</form>
