<?php
namespace App\Views;
?>
<h1>Reviewer assignment</h1>
<p class="muted">Assign reviewers to submissions and record conflicts of interest.</p>

<div class="grid grid--2">
  <section class="card">
    <h2>New assignment</h2>
    <form data-api-form data-json="true" method="post" action="/api/conf/reviewer_assignment" data-refresh="true" class="stack">
      <label>Submission
        <select name="submission_id" required>
          <option value="">Select a submission</option>
          <?php foreach ($all_submissions as $s): ?>
            <option value="<?= (int) $s['id'] ?>">#<?= (int) $s['id'] ?> â€” <?= e($s['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Reviewer
        <select name="reviewer_id" required>
          <option value="">Select a reviewer</option>
          <?php foreach ($reviewers as $r): ?>
            <option value="<?= (int) $r['id'] ?>"><?= e($r['name']) ?> (<?= e($r['email']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Conflict of interest
        <select name="conflict_of_interest">
          <option value="0">No</option>
          <option value="1">Yes</option>
        </select>
      </label>
      <label>Conflict note
        <input type="text" name="conflict_note" placeholder="e.g. former collaborator">
      </label>
      <button class="btn btn--primary" type="submit">Assign reviewer</button>
    </form>
  </section>

  <section class="card">
    <h2>Assignments</h2>
    <?php if (($assignments ?? []) === []): ?>
      <p class="muted">No assignments yet.</p>
    <?php else: ?>
      <table class="table">
        <thead><tr><th>Submission</th><th>Reviewer</th><th>Status</th><th>Conflict</th></tr></thead>
        <tbody>
        <?php foreach ($assignments as $a): ?>
          <tr>
            <td><?= e($a['submission_title'] ?? '') ?></td>
            <td><?= e($a['reviewer_name'] ?? '') ?></td>
            <td><?= status_label($a['status']) ?></td>
            <td><?= (int) $a['conflict_of_interest'] === 1 ? 'Yes' : 'No' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
</div>