<h1>Rebuttal</h1>
<?php if (!empty($errors)): ?><div class="alert error"><ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if (in_array('author', $user['roles'], true)): ?>
  <section class="card">
    <h2>Submit rebuttal</h2>
    <form method="post" action="/rebuttal/submit">
      <label>Submission
        <select name="submission_id" required>
          <?php foreach ($mySubmissions as $s): ?>
            <option value="<?= (int)$s['id'] ?>">#<?= (int)$s['id'] ?> - <?= htmlspecialchars($s['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Rebuttal text
        <textarea name="body" rows="6" required></textarea>
      </label>
      <button type="submit" class="btn">Submit</button>
    </form>
  </section>
<?php endif; ?>

<section>
  <h2>All rebuttals</h2>
  <?php if (empty($rebuttals)): ?>
    <p>No rebuttals yet.</p>
  <?php else: ?>
    <div class="rebuttal-list">
      <?php foreach ($rebuttals as $r): ?>
        <article class="card">
          <h3><?= htmlspecialchars($r['submission_title']) ?></h3>
          <p class="muted">By <?= htmlspecialchars($r['author_name']) ?> on <?= htmlspecialchars($r['created_at']) ?></p>
          <p><?= nl2br(htmlspecialchars($r['body'])) ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>