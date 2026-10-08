<?php
namespace App\Views;
?>
<?php $s = $submission; ?>
<h1>Submission detail</h1>
<section class="card">
  <h2><?= e($s['title']) ?></h2>
  <p class="muted">Status: <?= status_label($s['status']) ?> Â· Submitted: <?= date_fmt($s['created_at']) ?></p>
  <p><strong>Abstract</strong></p>
  <p><?= nl2br(e($s['abstract'])) ?></p>
  <p><strong>Keywords</strong>: <?= e($s['keywords']) ?></p>

  <?php if (isset($s['manuscript']) && is_array($s['manuscript']) && $s['manuscript'] !== []): ?>
    <p>
      <a class="btn" href="/api/conf/manuscript_access/<?= (int) $s['id'] ?>/download">Download manuscript</a>
      <a class="btn btn--ghost" href="/blind-views?submission_id=<?= (int) $s['id'] ?>">Blind / non-blind view</a>
    </p>
  <?php endif; ?>

  <?php if ($user['role'] === 'reviewer'): ?>
    <p><a class="btn" href="/reviewer">Back to reviewer workspace</a></p>
  <?php endif; ?>
</section>

<?php if (isset($blind_view) && is_array($blind_view) && isset($blind_view['review_comments'])): ?>
  <section class="card">
    <h2>Review feedback</h2>
    <p class="muted">Aggregate reviewer summary (double-blind: reviewer identities are hidden).</p>
    <?php if (isset($blind_view['review_summary'])): ?>
      <p>
        Submitted reviews: <strong><?= (int) $blind_view['review_summary']['count'] ?></strong> Â·
        Average score: <strong><?= e((string) $blind_view['review_summary']['average_score']) ?></strong> Â·
        Average confidence: <strong><?= e((string) $blind_view['review_summary']['average_confidence']) ?></strong>
      </p>
    <?php endif; ?>
    <?php if (($blind_view['review_comments'] ?? []) !== []): ?>
      <ul class="list">
        <?php foreach ($blind_view['review_comments'] as $comment): ?>
          <li><?= nl2br(e($comment['comments'])) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="muted">No submitted reviews yet.</p>
    <?php endif; ?>
  </section>
<?php endif; ?>

<?php if ($user['role'] === 'author' && $s['status'] === 'in_rebuttal'): ?>
  <section class="card">
    <h2>Submit a rebuttal</h2>
    <form data-api-form data-json="true" method="post" action="/api/conf/rebuttal" data-refresh="true" class="stack">
      <input type="hidden" name="submission_id" value="<?= (int) $s['id'] ?>">
      <label>Rebuttal text
        <textarea name="text" rows="6" required></textarea>
      </label>
      <button class="btn btn--primary" type="submit">Submit rebuttal</button>
    </form>
  </section>
<?php endif; ?>