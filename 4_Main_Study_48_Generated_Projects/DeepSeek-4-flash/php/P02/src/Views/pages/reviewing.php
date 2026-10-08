<?php
namespace App\Views;
?>
<h1>Reviewer workspace</h1>

<section class="card">
  <h2>My assignments</h2>
  <?php if (($assignments ?? []) === []): ?>
    <p class="muted">You have no assignments yet.</p>
  <?php else: ?>
    <table class="table">
      <thead><tr><th>Submission</th><th>Status</th><th>Conflict</th><th>My review</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($assignments as $a): ?>
        <tr>
          <td>
            <a href="/papers/<?= (int) $a['submission_id'] ?>"><?= e($a['submission_title']) ?></a>
            <div class="muted small"><?= status_label($a['submission_status']) ?></div>
          </td>
          <td><?= status_label($a['status']) ?></td>
          <td><?= (int) $a['conflict_of_interest'] === 1 ? 'Yes' : 'No' ?></td>
          <td>
            <?php if ($a['review_id'] !== null): ?>
              <?= status_label($a['review_status']) ?>
            <?php else: ?>
              <span class="muted">Not started</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($a['status'] === 'pending'): ?>
              <form data-api-form data-json="true" method="post" action="/api/conf/reviewer_assignment/<?= (int) $a['id'] ?>" data-method="PATCH" data-refresh="true" class="inline">
                <input type="hidden" name="status" value="accepted">
                <button class="btn btn--small" type="submit">Accept</button>
              </form>
              <form data-api-form data-json="true" method="post" action="/api/conf/reviewer_assignment/<?= (int) $a['id'] ?>" data-method="PATCH" data-refresh="true" class="inline">
                <input type="hidden" name="status" value="declined">
                <button class="btn btn--small btn--danger" type="submit">Decline</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<section class="card">
  <h2>My reviews</h2>
  <?php if (($reviews ?? []) === []): ?>
    <p class="muted">You have not written any reviews yet.</p>
  <?php else: ?>
    <?php foreach ($reviews as $review): ?>
      <div class="card card--inset">
        <h3><?= e($review['submission_title']) ?> <span class="muted small"><?= status_label($review['status']) ?></span></h3>
        <form data-api-form data-json="true" method="post" action="/api/conf/reviewing/<?= (int) $review['id'] ?>" data-method="PATCH" data-refresh="true" class="stack">
          <input type="hidden" name="review_id" value="<?= (int) $review['id'] ?>">
          <div class="row">
            <label>Score (1â€“10)
              <input type="number" name="score" min="1" max="10" value="<?= e((string) $review['score']) ?>" required>
            </label>
            <label>Confidence (1â€“5)
              <input type="number" name="confidence" min="1" max="5" value="<?= e((string) $review['confidence']) ?>" required>
            </label>
          </div>
          <label>Comments to authors
            <textarea name="comments" rows="4"><?= e($review['comments']) ?></textarea>
          </label>
          <label>Private notes (chair only)
            <textarea name="private_notes" rows="2"><?= e($review['private_notes']) ?></textarea>
          </label>
          <div class="row">
            <button class="btn btn--primary" type="submit">Save review</button>
            <?php if ($review['status'] === 'draft'): ?>
              <form data-api-form data-json="true" method="post" action="/api/conf/reviewing/<?= (int) $review['id'] ?>" data-method="PATCH" data-refresh="true" class="inline">
                <input type="hidden" name="status" value="submitted">
                <button class="btn" type="submit">Submit review</button>
              </form>
            <?php endif; ?>
          </div>
        </form>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</section>

<?php if ($user['role'] === 'reviewer'): ?>
  <section class="card">
    <h2>Start a new review</h2>
    <form data-api-form data-json="true" method="post" action="/api/conf/reviewing" data-refresh="true" class="stack">
      <label>Submission
        <select name="submission_id" required>
          <option value="">Select a submission assigned to you</option>
          <?php foreach ($assignments as $a): ?>
            <option value="<?= (int) $a['submission_id'] ?>">#<?= (int) $a['submission_id'] ?> â€” <?= e($a['submission_title']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <div class="row">
        <label>Score (1â€“10)
          <input type="number" name="score" min="1" max="10" required>
        </label>
        <label>Confidence (1â€“5)
          <input type="number" name="confidence" min="1" max="5" required>
        </label>
      </div>
      <label>Comments to authors
        <textarea name="comments" rows="4"></textarea>
      </label>
      <label>Private notes
        <textarea name="private_notes" rows="2"></textarea>
      </label>
      <button class="btn btn--primary" type="submit">Create review (draft)</button>
    </form>
  </section>
<?php endif; ?>