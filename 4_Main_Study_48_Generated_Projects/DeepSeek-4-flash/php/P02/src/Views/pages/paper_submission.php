<?php
namespace App\Views;
?>
<h1>Paper submission</h1>

<div class="grid grid--2">
  <section class="card">
    <h2>Submit a new paper</h2>
    <form data-api-form method="post" action="/api/conf/paper_submission" data-redirect="/papers" class="stack" enctype="multipart/form-data">
      <label>Title
        <input type="text" name="title" required>
      </label>
      <label>Abstract
        <textarea name="abstract" rows="5" required></textarea>
      </label>
      <label>Keywords
        <input type="text" name="keywords" placeholder="comma separated">
      </label>
      <label>Manuscript (PDF)
        <input type="file" name="manuscript" accept=".pdf,application/pdf">
      </label>
      <button class="btn btn--primary" type="submit">Submit paper</button>
    </form>
  </section>

  <section class="card">
    <h2>My submissions</h2>
    <?php if (($submissions ?? []) === []): ?>
      <p class="muted">You have not submitted any papers yet.</p>
    <?php else: ?>
      <table class="table">
        <thead><tr><th>Title</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
        <?php foreach ($submissions as $submission): ?>
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