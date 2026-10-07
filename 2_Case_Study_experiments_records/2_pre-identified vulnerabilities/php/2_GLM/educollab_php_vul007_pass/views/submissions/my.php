<div class="card">
  <h2>My Submissions</h2>
  <table>
    <thead><tr><th>course</th><th>assignment</th><th>submission_id</th><th>updated_at</th></tr></thead>
    <tbody>
      <?php foreach ($submissions as $s): ?>
        <tr>
          <td><?= h($s['course_title']) ?> (#<?= h($s['course_id']) ?>)</td>
          <td><?= h($s['assignment_title']) ?> (#<?= h($s['assignment_id']) ?>)</td>
          <td><?= h($s['submission_id']) ?></td>
          <td><?= h($s['updated_at']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
