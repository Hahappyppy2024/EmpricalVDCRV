<?php
namespace App\Views;
?>
<h1>Dashboard</h1>
<p class="muted">Signed in as <strong><?= e($user['name']) ?></strong> (<?= e($user['role']) ?>).</p>

<div class="grid grid--4">
  <div class="stat"><span class="stat__value"><?= (int) $summary['submissions'] ?></span><span class="stat__label">Visible submissions</span></div>
  <div class="stat"><span class="stat__value"><?= (int) $summary['reviews'] ?></span><span class="stat__label">Reviews</span></div>
  <div class="stat"><span class="stat__value"><?= (int) $summary['pending_assignments'] ?></span><span class="stat__label">Assignments</span></div>
  <?php if (in_array($user['role'], ['chair', 'admin'], true)): ?>
    <div class="stat"><span class="stat__value"><?= (int) $summary['exports'] ?></span><span class="stat__label">Exports</span></div>
  <?php else: ?>
    <div class="stat"><span class="stat__value"><?= (int) $summary['decisions'] ?></span><span class="stat__label">Decisions</span></div>
  <?php endif; ?>
</div>

<div class="grid grid--2">
  <section class="card">
    <h2>Conference phases</h2>
    <table class="table">
      <thead><tr><th>Phase</th><th>Status</th><th>Dates</th></tr></thead>
      <tbody>
      <?php foreach ($phases as $phase): ?>
        <tr>
          <td><?= e($phase['name']) ?></td>
          <td><?= status_label($phase['status']) ?></td>
          <td><?= e($phase['start_date']) ?> â†’ <?= e($phase['end_date']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <section class="card">
    <h2>Recent submissions</h2>
    <?php if ($recent === []): ?>
      <p class="muted">No submissions yet. Use <a href="/papers">Paper submission</a>.</p>
    <?php else: ?>
      <table class="table">
        <thead><tr><th>Title</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
        <?php foreach ($recent as $submission): ?>
          <tr>
            <td><a href="/papers/<?= (int) $submission['id'] ?>"><?= e($submission['title']) ?></a></td>
            <td><?= status_label($submission['status']) ?></td>
            <td><?= date_fmt($submission['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
</div>

<section class="card">
  <h2>Quick actions</h2>
  <p>
    <a class="btn" href="/papers">Submit a paper</a>
    <a class="btn" href="/discovery">Search submissions</a>
    <?php if (in_array($user['role'], ['reviewer', 'chair', 'admin'], true)): ?>
      <a class="btn" href="/reviewer">Reviewer workspace</a>
    <?php endif; ?>
    <?php if (in_array($user['role'], ['chair', 'admin'], true)): ?>
      <a class="btn" href="/chair">Chair workspace</a>
    <?php endif; ?>
  </p>
</section>