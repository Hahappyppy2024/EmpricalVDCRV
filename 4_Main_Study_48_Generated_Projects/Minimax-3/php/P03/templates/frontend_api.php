<?php $page_title = $page_title ?? 'Frontend API'; ?>
<section>
  <h1>Frontend API integration</h1>
  <p>This page demonstrates the asynchronous frontend integration for SHOP-13.</p>

  <h2>Form pre-check</h2>
  <form id="preCheckForm" class="form">
    <label>Full name <input type="text" name="full_name" required></label>
    <label>Line 1 <input type="text" name="line1" required></label>
    <label>City <input type="text" name="city" required></label>
    <label>Postal code <input type="text" name="postal_code" required></label>
    <button class="btn btn-primary" type="submit">Validate</button>
    <pre id="preCheckResult" class="result"></pre>
  </form>

  <h2>Payment simulation</h2>
  <form id="paymentForm" class="form">
    <label>Method <input type="text" name="method" value="card"></label>
    <label>Token <input type="text" name="token" value="tok_ok"></label>
    <label>Amount (cents) <input type="number" name="amount_cents" value="1000"></label>
    <button class="btn btn-primary" type="submit">Simulate</button>
    <pre id="paymentResult" class="result"></pre>
  </form>

  <h2>Order state</h2>
  <p><a class="btn btn-ghost" href="/api/shop/frontend_api_integration" data-fetch="true">Snapshot</a></p>
  <pre id="snapshot" class="result"></pre>

  <h2>Existing orders (poll)</h2>
  <ul>
    <?php foreach ($orders as $o): ?>
      <li><?= htmlspecialchars($o['reference'], ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($o['status'], ENT_QUOTES, 'UTF-8') ?> — $<?= number_format((int)$o['total_cents'] / 100, 2) ?></li>
    <?php endforeach; ?>
  </ul>
</section>