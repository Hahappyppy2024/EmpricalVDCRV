<div class="card">
  <h2>Materials (course <?= h($course['course_id']) ?>)</h2>

  <p>
    <a href="/courses/<?= h($course['course_id']) ?>/materials/upload-zip">Upload ZIP</a>
    |
    <a href="/courses/<?= h($course['course_id']) ?>/materials/download-zip">Download as ZIP</a>
  </p>

  <h3>Uploaded ZIP Records</h3>
  <?php if (empty($items)): ?>
    <p class="muted">No uploads yet.</p>
  <?php else: ?>
    <table>
      <thead><tr><th>material_id</th><th>uploader</th><th>created_at</th><th>path</th></tr></thead>
      <tbody>
        <?php foreach ($items as $it): ?>
          <tr>
            <td><?= h($it['material_id']) ?></td>
            <td><?= h($it['uploader_username']) ?></td>
            <td><?= h($it['created_at']) ?></td>
            <td><?= h($it['extracted_path']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <h3>Extracted Files</h3>
  <?php if (empty($files)): ?>
    <p class="muted">No extracted files yet.</p>
  <?php else: ?>
    <ul>
      <?php foreach ($files as $f): ?>
        <li><?= h($f) ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
