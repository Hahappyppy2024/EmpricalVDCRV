<?php $pageTitle = 'Review moderation'; ?>
<h1>Review moderation</h1>
<div class="tabs">
  <a href="/admin">Overview</a>
  <a href="/admin/users">Users</a>
  <a href="/admin/settings">Settings</a>
  <a href="/admin/reviews">Reviews</a>
  <a href="/admin/audit">Audit log</a>
</div>

<section class="section">
  <h2>Pending reviews (<?= count($pending) ?>)</h2>
  <?php if ($pending === []): ?>
    <div class="empty">No reviews waiting for moderation.</div>
  <?php else: ?>
    <?php foreach ($pending as $r): ?>
      <div class="card review">
        <div>
          <strong><?= e($r['customer_name']) ?></strong> on <em><?= e($r['product_name']) ?></em>
          <span class="stars"><?= str_repeat('★', (int) $r['rating']) . str_repeat('☆', 5 - (int) $r['rating']) ?></span>
        </div>
        <?php if ($r['title'] !== ''): ?><h4><?= e($r['title']) ?></h4><?php endif; ?>
        <p><?= e($r['text']) ?></p>
        <form method="post" action="/admin/reviews/<?= (int) $r['id'] ?>/moderate" class="inline-form">
          <?= csrf_field($csrf) ?>
          <button class="btn btn-sm" name="decision" value="approve" type="submit">Approve</button>
          <button class="btn btn-sm btn-danger" name="decision" value="reject" type="submit">Reject</button>
        </form>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</section>

<section class="section">
  <h2>Recent reviews</h2>
  <table class="table">
    <thead><tr><th>Review</th><th>Product</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($recent as $r): ?>
      <tr>
        <td><?= e($r['customer_name']) ?>: <?= e(mb_strimwidth((string) $r['text'], 0, 60, '…')) ?></td>
        <td><?= e($r['product_name']) ?></td>
        <td><span class="badge"><?= e($r['status']) ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>
