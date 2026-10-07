<div class="card">
  <h2>Upload File (course <?= h($course_id) ?>)</h2>
  <form method="post" action="/courses/<?= h($course_id) ?>/uploads" enctype="multipart/form-data">
    <input type="file" name="file" />
    <button type="submit">Upload</button>
  </form>
</div>
