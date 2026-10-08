<?php
namespace App\Views;
?>
<?php $submissionId = isset($_GET['submission_id']) ? (int) $_GET['submission_id'] : null; ?>
<h1>Double-blind views</h1>
<p class="muted">The system presents role-specific anonymized submission views: reviewers see blind views (author identity hidden), authors see an aggregated non-blind view, and chairs see full non-blind views.</p>

<section class="card">
  <h2>Inspect a submission view</h2>
  <form class="row" method="get">
    <label class="grow">Submission ID
      <input type="number" name="submission_id" min="1" value="<?= e((string) $submissionId) ?>" required>
    </label>
    <button class="btn btn--primary" type="submit">Show view</button>
  </form>
</section>

<?php if ($submissionId !== null): ?>
  <section class="card" data-blind-view data-submission="<?= (int) $submissionId ?>">
    <h2>View for submission #<?= (int) $submissionId ?></h2>
    <p class="muted">Loading role-specific anonymized viewâ€¦</p>
  </section>
<?php endif; ?>

<section class="card">
  <h2>Recent blind view records</h2>
  <?php if (($views ?? []) === []): ?>
    <p class="muted">No blind view records yet.</p>
  <?php else: ?>
    <table class="table">
      <thead><tr><th>ID</th><th>Submission</th><th>Viewer</th><th>Role</th><th>Blind</th><th>When</th></tr></thead>
      <tbody>
      <?php foreach ($views as $view): ?>
        <tr>
          <td><?= (int) $view['id'] ?></td>
          <td>#<?= (int) $view['submission_id'] ?></td>
          <td>#<?= (int) $view['viewer_id'] ?></td>
          <td><?= e($view['viewer_role']) ?></td>
          <td><?= (int) $view['blind'] === 1 ? 'Yes' : 'No' ?></td>
          <td><?= date_fmt($view['viewed_at']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>