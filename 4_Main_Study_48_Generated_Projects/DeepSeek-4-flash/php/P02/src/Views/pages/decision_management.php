<?php
namespace App\Views;
?>
<h1>Decision management</h1>
<p class="muted">Record accept/reject decisions. Authors are notified through the local mail adapter; every action is audited.</p>

<div class="grid grid--2">
  <section class="card">
    <h2>Record a decision</h2>
    <form data-api-form data-json="true" method="post" action="/api/conf/decision_management" data-refresh="true" class="stack">
      <label>Submission
        <select name="submission_id" required>
          <option value="">Select a submission</option>
          <?php foreach ($all_submissions as $s): ?>
            <option value="<?= (int) $s['id'] ?>">#<?= (int) $s['id'] ?> â€” <?= e($s['title']) ?> (<?= e($s['status']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Decision
        <select name="decision" required>
          <option value="accept">Accept</option>
          <option value="reject">Reject</option>
          <option value="major_revision">Major revision</option>
          <option value="minor_revision">Minor revision</option>
        </select>
      </label>
      <label>Notification to authors
        <textarea name="notification_text" rows="3"></textarea>
      </label>
      <button class="btn btn--primary" type="submit">Record decision</button>
    </form>
  </section>

  <section class="card">
    <h2>Recorded decisions</h2>
    <?php if (($decisions ?? []) === []): ?>
      <p class="muted">No decisions recorded yet.</p>
    <?php else: ?>
      <table class="table">
        <thead><tr><th>Submission</th><th>Decision</th><th>By</th><th>When</th></tr></thead>
        <tbody>
        <?php foreach ($decisions as $d): ?>
          <tr>
            <td><?= e($d['submission_title'] ?? '') ?></td>
            <td><?= status_label($d['decision']) ?></td>
            <td><?= e($d['decided_by_name'] ?? '') ?></td>
            <td><?= date_fmt($d['decided_at'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
</div>