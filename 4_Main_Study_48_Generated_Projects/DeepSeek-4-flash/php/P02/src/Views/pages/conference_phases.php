<?php
namespace App\Views;
?>
<h1>Conference phases</h1>
<p class="muted">The chair configures submission, review, rebuttal, and decision phases. Workflows are gated on the phase status.</p>

<div class="card">
  <h2>Phase list</h2>
  <table class="table">
    <thead><tr><th>ID</th><th>Name</th><th>Status</th><th>Start</th><th>End</th><th>Update</th></tr></thead>
    <tbody>
    <?php foreach ($phases as $phase): ?>
      <tr>
        <td><?= (int) $phase['id'] ?></td>
        <td><?= e($phase['name']) ?></td>
        <td><?= status_label($phase['status']) ?></td>
        <td><?= e($phase['start_date']) ?></td>
        <td><?= e($phase['end_date']) ?></td>
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
</div>

<div class="card">
  <h2>Add a phase</h2>
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
    <button class="btn btn--primary" type="submit">Create phase</button>
  </form>
</div>