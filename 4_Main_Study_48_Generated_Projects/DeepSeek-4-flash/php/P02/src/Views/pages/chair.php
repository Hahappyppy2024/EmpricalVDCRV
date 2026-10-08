<?php
namespace App\Views;
?>
<h1>Chair workspace</h1>
<p class="muted">Conference setup, editorial phases, reviewer assignment, decisions, and exports.</p>

<div class="card">
  <h2>Conference phases</h2>
  <table class="table">
    <thead><tr><th>Phase</th><th>Status</th><th>Dates</th><th>Update</th></tr></thead>
    <tbody>
    <?php foreach ($phases as $phase): ?>
      <tr>
        <td><?= e($phase['name']) ?></td>
        <td><?= status_label($phase['status']) ?></td>
        <td><?= e($phase['start_date']) ?> â†’ <?= e($phase['end_date']) ?></td>
        <td>
          <form data-api-form data-json="true" method="post" action="/api/conf/conference_phases/<?= (int) $phase['id'] ?>" data-method="PATCH" data-refresh="true" class="inline">
            <select name="status">
              <option value="open" <?= $phase['status'] === 'open' ? 'selected' : '' ?>>Open</option>
              <option value="closed" <?= $phase['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
            </select>
            <button class="btn btn--small" type="submit">Save</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <details>
    <summary>Add a phase</summary>
    <form data-api-form data-json="true" method="post" action="/api/conf/conference_phases" data-refresh="true" class="stack">
      <div class="row">
        <label>Name
          <select name="name">
            <option value="submission">submission</option>
            <option value="review">review</option>
            <option value="rebuttal">rebuttal</option>
            <option value="decision">decision</option>
          </select>
        </label>
        <label>Status
          <select name="status"><option value="open">open</option><option value="closed">closed</option></select>
        </label>
        <label>Start date <input type="date" name="start_date" required></label>
        <label>End date <input type="date" name="end_date" required></label>
      </div>
      <button class="btn" type="submit">Create phase</button>
    </form>
  </details>
</div>

<div class="card">
  <h2>Reviewer assignment</h2>
  <form data-api-form data-json="true" method="post" action="/api/conf/reviewer_assignment" data-refresh="true" class="stack">
    <div class="row">
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
    </div>
    <label>Conflict note
      <input type="text" name="conflict_note" placeholder="e.g. former collaborator">
    </label>
    <button class="btn" type="submit">Assign reviewer</button>
  </form>
  <?php if (($assignments ?? []) !== []): ?>
    <table class="table table--top">
      <thead><tr><th>ID</th><th>Submission</th><th>Reviewer</th><th>Status</th><th>Conflict</th></tr></thead>
      <tbody>
      <?php foreach ($assignments as $a): ?>
        <tr>
          <td><?= (int) $a['id'] ?></td>
          <td><?= e($a['submission_title'] ?? '') ?></td>
          <td><?= e($a['reviewer_name'] ?? '') ?></td>
          <td><?= status_label($a['status']) ?></td>
          <td><?= (int) $a['conflict_of_interest'] === 1 ? 'Yes' : 'No' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Decision management</h2>
  <form data-api-form data-json="true" method="post" action="/api/conf/decision_management" data-refresh="true" class="stack">
    <div class="row">
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
    </div>
    <label>Notification to authors
      <textarea name="notification_text" rows="2"></textarea>
    </label>
    <button class="btn" type="submit">Record decision</button>
  </form>
  <?php if (($decisions ?? []) !== []): ?>
    <table class="table table--top">
      <thead><tr><th>ID</th><th>Submission</th><th>Decision</th><th>Decided by</th><th>When</th></tr></thead>
      <tbody>
      <?php foreach ($decisions as $d): ?>
        <tr>
          <td><?= (int) $d['id'] ?></td>
          <td><?= e($d['submission_title'] ?? '') ?></td>
          <td><?= status_label($d['decision']) ?></td>
          <td><?= e($d['decided_by_name'] ?? '') ?></td>
          <td><?= date_fmt($d['decided_at'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Bulk exports</h2>
  <form data-api-form data-json="true" method="post" action="/api/conf/bulk_exports" data-refresh="true" class="row">
    <label>Type
      <select name="export_type">
        <option value="submissions">Submissions</option>
        <option value="reviews">Reviews</option>
        <option value="decisions">Decisions</option>
      </select>
    </label>
    <label>Format
      <select name="format">
        <option value="csv">CSV</option>
        <option value="pdf">PDF</option>
      </select>
    </label>
    <button class="btn" type="submit">Generate export</button>
  </form>
  <?php if (($exports ?? []) !== []): ?>
    <table class="table table--top">
      <thead><tr><th>ID</th><th>Type</th><th>Format</th><th>Status</th><th>When</th><th>Download</th></tr></thead>
      <tbody>
      <?php foreach ($exports as $e): ?>
        <tr>
          <td><?= (int) $e['id'] ?></td>
          <td><?= e($e['export_type']) ?></td>
          <td><?= e(strtoupper($e['format'])) ?></td>
          <td><?= status_label($e['status']) ?></td>
          <td><?= date_fmt($e['created_at']) ?></td>
          <td>
            <?php if ($e['status'] === 'completed'): ?>
              <a class="btn btn--small" href="/api/conf/bulk_exports/<?= (int) $e['id'] ?>/download">Download</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Audit trail</h2>
  <?php if (($audit ?? []) === []): ?>
    <p class="muted">No audit events yet.</p>
  <?php else: ?>
    <table class="table">
      <thead><tr><th>When</th><th>User</th><th>Action</th><th>Entity</th><th>Detail</th></tr></thead>
      <tbody>
      <?php foreach ($audit as $ev): ?>
        <tr>
          <td><?= date_fmt($ev['created_at']) ?></td>
          <td>#<?= (int) $ev['user_id'] ?></td>
          <td><?= e($ev['action']) ?></td>
          <td><?= e($ev['entity']) ?>#<?= (int) $ev['entity_id'] ?></td>
          <td><?= e($ev['detail']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>