<?php
namespace App\Views;
?>
<h1>Manuscript access</h1>
<p class="muted">Authorized users may view or download manuscripts and supplementary files. Every access is recorded.</p>

<section class="card">
  <h2>Access a manuscript</h2>
  <form data-api-form data-json="true" method="post" action="/api/conf/manuscript_access" data-refresh="true" class="stack">
    <div class="row">
      <label>Submission ID
        <input type="number" name="submission_id" min="1" required>
      </label>
      <label>Access type
        <select name="access_type">
          <option value="view">View</option>
          <option value="download">Download</option>
        </select>
      </label>
    </div>
    <button class="btn btn--primary" type="submit">Access manuscript</button>
  </form>
</section>

<section class="card">
  <h2>Access log</h2>
  <?php if (($records ?? []) === []): ?>
    <p class="muted">No access records yet.</p>
  <?php else: ?>
    <table class="table">
      <thead><tr><th>ID</th><th>Submission</th><th>File</th><th>Type</th><th>Accessed by</th><th>When</th></tr></thead>
      <tbody>
      <?php foreach ($records as $record): ?>
        <tr>
          <td><?= (int) $record['id'] ?></td>
          <td>#<?= (int) $record['submission_id'] ?></td>
          <td>#<?= (int) $record['file_id'] ?></td>
          <td><?= status_label($record['access_type']) ?></td>
          <td>#<?= (int) $record['accessed_by'] ?></td>
          <td><?= date_fmt($record['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>