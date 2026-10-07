<div class="card">
  <h2>Upload ZIP (course <?= h($course['course_id']) ?>)</h2>
  <form method="post" action="/courses/<?= h($course['course_id']) ?>/materials/upload-zip" enctype="multipart/form-data">
    <label>ZIP file: <input type="file" name="zip_file" required></label>
    <button type="submit">Upload</button>
  </form>
  <p><a href="/courses/<?= h($course['course_id']) ?>/materials">Back</a></p>
</div>
