<?php $pageTitle = 'Platform settings'; ?>
<h1>Platform settings</h1>
<div class="tabs">
  <a href="/admin">Overview</a>
  <a href="/admin/users">Users</a>
  <a href="/admin/settings">Settings</a>
  <a href="/admin/reviews">Reviews</a>
  <a href="/admin/audit">Audit log</a>
</div>

<div class="card form">
  <form method="post" action="/admin/settings">
    <?= csrf_field($csrf) ?>
    <label>Shop name <input type="text" name="shop_name" value="<?= e((string) ($settings['shop_name'] ?? '')) ?>"></label>
    <label>Currency <input type="text" name="currency" value="<?= e((string) ($settings['currency'] ?? 'USD')) ?>"></label>
    <label>Flat shipping <input type="number" step="0.01" name="shipping_flat" value="<?= e((string) ($settings['shipping_flat'] ?? '5.00')) ?>"></label>
    <label>Free shipping threshold <input type="number" step="0.01" name="free_shipping_threshold" value="<?= e((string) ($settings['free_shipping_threshold'] ?? '100')) ?>"></label>
    <label>Tax rate <input type="number" step="0.001" name="tax_rate" value="<?= e((string) ($settings['tax_rate'] ?? '0.08')) ?>"></label>
    <button class="btn btn-primary" type="submit">Save settings</button>
  </form>
</div>
