<h1>Paper Submission</h1>
<?php if (!empty($errors)): ?>
  <div class="alert error"><ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<?php if (!empty($success)): ?><div class="alert success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if (!$phaseOpen): ?>
  <div class="alert warning">Submission phase is currently <strong>closed</strong>. Contact the chair to open the phase.</div>
<?php else: ?>
  <section class="card">
    <h2>Submit a new paper</h2>
    <form action="/paper_submission" method="post" enctype="multipart/form-data">
      <label>Title <input type="text" name="title" required></label>
      <label>Topic <input type="text" name="topic" placeholder="e.g. Systems"></label>
      <label>Abstract
        <textarea name="abstract" rows="6" required></textarea>
      </label>
      <label>Keywords (comma-separated) <input type="text" name="keywords" required></label>
      <label>Manuscript PDF (max 5MB) <input type="file" name="pdf" accept="application/pdf" required></label>
      <button type="submit" class="btn">Submit</button>
    </form>
  </section>
<?php endif; ?>

<section>
  <h2>My submissions</h2>
  <?php if (empty($mySubs)): ?>
    <p>No submissions yet.</p>
  <?php else: ?>
    <table class="data-table">
      <thead><tr><th>ID</th><th>Title</th><th>Topic</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($mySubs as $s): ?>
          <tr>
            <td>#<?= (int)$s['id'] ?></td>
            <td><?= htmlspecialchars($s['title']) ?></td>
            <td><?= htmlspecialchars($s['topic']) ?></td>
            <td><span class="badge"><?= htmlspecialchars($s['status']) ?></span></td>
            <td>
              <a href="/manuscript_access/<?= (int)$s['id'] ?>">Files</a>
              <a href="/double_blind_views/<?= (int)$s['id'] ?>">View</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>