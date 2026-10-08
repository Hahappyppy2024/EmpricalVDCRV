<?php
namespace App\Views;
?>
<h1>Bulk exports</h1>
<p class="muted">Export submissions, reviews, and decision summaries as CSV or PDF. Files are generated locally in <code>storage/exports</code>.</p>

<div class="card">
  <h2>Generate an export</h2>
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
    <button class="btn btn--primary" type="submit">Generate export</button>
  </form>
</div>

<section class="card">
  <h2>Exports</h2>
  <?php if (($exports ?? []) === []): ?>
    <p class="muted">No exports generated yet.</p>
  <?php else: ?>
    <table class="table">
      <thead><tr><th>ID</th><th>Type</th><th>Format</th><th>Status</th><th>Requested by</th><th>When</th><th>Download</th></tr></thead>
      <tbody>
      <?php foreach ($exports as $e): ?>
        <tr>
          <td><?= (int) $e['id'] ?></td>
          <td><?= e($e['export_type']) ?></td>
          <td><?= e(strtoupper($e['format'])) ?></td>
          <td><?= status_label($e['status']) ?></td>
          <td><?= e($e['requested_by_name'] ?? '') ?></td>
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
</section>