<div class="card">
  <h2>Uploads (course <?= h($course_id) ?>)</h2>
  <a href="/courses/<?= h($course_id) ?>/uploads/new">Upload file (staff)</a>
  <table>
    <thead><tr><th>upload_id</th><th>name</th><th>uploaded_by</th><th>actions</th></tr></thead>
    <tbody>
      <?php foreach ($uploads as $u): ?>
        <tr>
          <td><?= h($u['upload_id']) ?></td>
          <td><?= h($u['original_name']) ?></td>
          <td><?= h($u['uploaded_by_username']) ?></td>
          <td>
            <a href="/courses/<?= h($course_id) ?>/uploads/<?= h($u['upload_id']) ?>/download">Download</a>
            <form style="display:inline" method="post" action="/courses/<?= h($course_id) ?>/uploads/<?= h($u['upload_id']) ?>/delete"><button type="submit">Delete (staff)</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
