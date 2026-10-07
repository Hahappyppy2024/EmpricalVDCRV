<div class="card">
  <h2>Submissions (course <?= h($course_id) ?>, assignment <?= h($assignment_id) ?>)</h2>
  <table>
    <thead><tr><th>submission_id</th><th>student</th><th>content</th><th>attachment_upload_id</th><th>updated_at</th></tr></thead>
    <tbody>
      <?php foreach ($submissions as $s): ?>
        <tr>
          <td><?= h($s['submission_id']) ?></td>
          <td><?= h($s['student_username']) ?></td>
          <td><?= h($s['content_text']) ?></td>
          <td><?= h($s['attachment_upload_id'] ?? '') ?></td>
          <td><?= h($s['updated_at']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
