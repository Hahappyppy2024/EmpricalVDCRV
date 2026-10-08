<?php $pageTitle = 'Promotions'; ?>
<h1>Promotions</h1>
<div class="tabs">
  <a href="/seller">Overview</a>
  <a href="/seller/products">Products</a>
  <a href="/seller/orders">Orders</a>
  <a href="/seller/inventory">Inventory</a>
  <a href="/seller/reports">Reports</a>
  <a href="/seller/promotions">Promotions</a>
</div>

<section class="section">
  <h2>Existing promotions</h2>
  <table class="table">
    <thead><tr><th>Code</th><th>Type</th><th>Amount</th><th>Active</th><th>Uses</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($promotions as $pr): ?>
      <tr>
        <td><strong><?= e($pr['code']) ?></strong><br><span class="muted small"><?= e($pr['description']) ?></span></td>
        <td><?= e($pr['discount_type']) ?></td>
        <td><?= $pr['discount_type'] === 'percent' ? (float) $pr['amount'] . '%' : money((float) $pr['amount'], $currency) ?></td>
        <td><?= (int) $pr['active'] === 1 ? 'yes' : 'no' ?></td>
        <td><?= (int) $pr['uses'] ?><?= $pr['max_uses'] !== null ? ' / ' . (int) $pr['max_uses'] : '' ?></td>
        <td>
          <form method="post" action="/seller/promotions/<?= (int) $pr['id'] ?>/toggle">
            <?= csrf_field($csrf) ?>
            <button class="btn btn-sm" type="submit"><?= (int) $pr['active'] === 1 ? 'Deactivate' : 'Activate' ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>

<section class="card form">
  <h2>Create a promotion</h2>
  <form method="post" action="/seller/promotions">
    <?= csrf_field($csrf) ?>
    <label>Code <input type="text" name="code" placeholder="SPRING20" required></label>
    <label>Description <input type="text" name="description"></label>
    <div class="row2">
      <label>Type
        <select name="discount_type">
          <option value="percent">Percent</option>
          <option value="fixed">Fixed amount</option>
        </select>
      </label>
      <label>Amount <input type="number" step="0.01" min="0" name="amount" required></label>
    </div>
    <div class="row2">
      <label>Max uses <input type="number" min="1" name="max_uses" placeholder="unlimited"></label>
      <label>Active <select name="active"><option value="1">Yes</option><option value="0">No</option></select></label>
    </div>
    <button class="btn btn-primary" type="submit">Create promotion</button>
  </form>
</section>
