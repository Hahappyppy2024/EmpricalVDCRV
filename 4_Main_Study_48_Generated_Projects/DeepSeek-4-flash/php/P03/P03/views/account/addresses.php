<?php $pageTitle = 'My addresses'; ?>
<h1>My addresses</h1>
<div class="tabs">
  <a href="/account">Profile</a>
  <a href="/account/addresses">Addresses</a>
  <a href="/account/orders">Orders</a>
</div>

<section class="section">
  <h2>Saved addresses</h2>
  <?php if ($addresses === []): ?>
    <div class="empty">No saved addresses yet.</div>
  <?php else: ?>
    <?php foreach ($addresses as $a): ?>
      <div class="card address">
        <div>
          <strong><?= e($a['label']) ?></strong><br>
          <?= e($a['line1']) ?><?= $a['line2'] !== '' ? ', ' . e($a['line2']) : '' ?><br>
          <?= e($a['city']) ?>, <?= e($a['postal_code']) ?> <?= e($a['country']) ?><br>
          <?= e($a['phone']) ?>
        </div>
        <form method="post" action="/account/addresses/<?= (int) $a['id'] ?>/delete">
          <?= csrf_field($csrf) ?>
          <button class="btn btn-sm btn-danger" type="submit">Delete</button>
        </form>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</section>

<section class="card form">
  <h2>Add an address</h2>
  <form method="post" action="/account/addresses">
    <?= csrf_field($csrf) ?>
    <label>Label <input type="text" name="label" value="Home"></label>
    <label>Address line 1 <input type="text" name="line1" required></label>
    <label>Address line 2 <input type="text" name="line2"></label>
    <div class="row2">
      <label>City <input type="text" name="city" required></label>
      <label>Postal code <input type="text" name="postal_code" required></label>
    </div>
    <label>Country <input type="text" name="country" value="US"></label>
    <label>Phone <input type="text" name="phone"></label>
    <button class="btn btn-primary" type="submit">Add address</button>
  </form>
</section>
