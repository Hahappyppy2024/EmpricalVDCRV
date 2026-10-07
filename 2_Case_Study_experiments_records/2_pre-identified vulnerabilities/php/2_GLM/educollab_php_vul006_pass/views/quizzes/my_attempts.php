<div class="card">
  <h2>My Quiz Attempts</h2>
  <table>
    <thead><tr><th>attempt_id</th><th>course</th><th>quiz</th><th>started_at</th><th>submitted_at</th></tr></thead>
    <tbody>
      <?php foreach ($attempts as $a): ?>
        <tr>
          <td><?= h($a['attempt_id']) ?></td>
          <td><?= h($a['course_title']) ?></td>
          <td><?= h($a['quiz_title']) ?></td>
          <td><?= h($a['started_at']) ?></td>
          <td><?= h($a['submitted_at'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
