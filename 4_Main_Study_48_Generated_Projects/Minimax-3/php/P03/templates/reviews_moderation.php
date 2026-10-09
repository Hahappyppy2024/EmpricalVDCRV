<?php $page_title = $page_title ?? 'Review moderation'; ?>
<section>
  <h1>Review moderation</h1>
  <?php if (!$pending): ?>
    <p>No reviews pending moderation.</p>
  <?php else: ?>
    <ul class="reviews">
      <?php foreach ($pending as $r): ?>
        <li>
          <p class="muted">Product: <?= htmlspecialchars($r['product_name'], ENT_QUOTES, 'UTF-8') ?> · Author: <?= htmlspecialchars($r['author'], ENT_QUOTES, 'UTF-8') ?> · Rating <?= (int)$r['rating'] ?></p>
          <p><?= nl2br(htmlspecialchars($r['body'], ENT_QUOTES, 'UTF-8')) ?></p>
          <form method="post" action="/reviews/moderation/<?= (int)$r['id'] ?>" class="inline">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <button class="btn btn-primary" type="submit" name="status" value="approved">Approve</button>
            <button class="btn btn-danger" type="submit" name="status" value="rejected">Reject</button>
          </form>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>