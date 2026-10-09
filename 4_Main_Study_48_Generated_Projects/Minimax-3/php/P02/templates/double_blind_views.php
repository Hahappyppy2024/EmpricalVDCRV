<h1>Double-Blind View</h1>
<p>Submission: <strong><?= htmlspecialchars($submission['title']) ?></strong></p>

<section class="card">
  <h2>Author identity</h2>
  <?php if ($showIdentity): ?>
    <p><strong><?= htmlspecialchars($author['display_name']) ?></strong> (<?= htmlspecialchars($author['affiliation']) ?>)</p>
  <?php else: ?>
    <p class="muted">Identity hidden — blinded view.</p>
  <?php endif; ?>
  <form method="post" action="/double_blind_views/<?= (int)$submission['id'] ?>">
    <input type="hidden" name="view_mode" value="<?= $showIdentity ? 'blind' : 'unblinded' ?>">
    <button type="submit" class="btn">Switch to <?= $showIdentity ? 'blind' : 'unblinded' ?> view</button>
  </form>
</section>

<section class="card">
  <h2>Paper details</h2>
  <p><strong>Topic:</strong> <?= htmlspecialchars($submission['topic']) ?></p>
  <p><strong>Keywords:</strong> <?= htmlspecialchars($submission['keywords']) ?></p>
  <h3>Abstract</h3>
  <p><?= nl2br(htmlspecialchars($submission['abstract'])) ?></p>
</section>

<section>
  <p><a class="btn" href="/manuscript_access/<?= (int)$submission['id'] ?>">Download manuscript</a></p>
</section>