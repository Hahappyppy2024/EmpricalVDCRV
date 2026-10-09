<h1>Submit Review</h1>
<p>Paper: <strong><?= htmlspecialchars($assignment['submission_title']) ?></strong></p>
<p><em><?= htmlspecialchars($assignment['abstract']) ?></em></p>
<?php if (!empty($errors)): ?>
  <div class="alert error"><ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<?php if (!empty($success)): ?><div class="alert success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<form method="post" action="/reviewing/<?= (int)$assignment['id'] ?>/submit">
  <label>Score (1-7)
    <input type="number" name="score" min="1" max="7" value="<?= htmlspecialchars((string)($review['score'] ?? '')) ?>" required>
  </label>
  <label>Confidence (1-5)
    <input type="number" name="confidence" min="1" max="5" value="<?= htmlspecialchars((string)($review['confidence'] ?? '')) ?>" required>
  </label>
  <label>Recommendation
    <select name="recommendation" required>
      <?php foreach (['accept', 'weak_accept', 'borderline', 'weak_reject', 'reject'] as $r): ?>
        <option value="<?= $r ?>" <?= ($review['recommendation'] ?? '') === $r ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $r)) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Comments to author
    <textarea name="comments_to_author" rows="6" required><?= htmlspecialchars($review['comments_to_author'] ?? '') ?></textarea>
  </label>
  <label>Comments to chair
    <textarea name="comments_to_chair" rows="4"><?= htmlspecialchars($review['comments_to_chair'] ?? '') ?></textarea>
  </label>
  <button type="submit" class="btn">Submit review</button>
</form>