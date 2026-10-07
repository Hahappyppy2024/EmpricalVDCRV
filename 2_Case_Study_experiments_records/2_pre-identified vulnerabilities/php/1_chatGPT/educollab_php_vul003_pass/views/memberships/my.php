<div class="card">
  <h2>My Memberships</h2>
  <table>
    <thead><tr><th>course</th><th>role</th><th>link</th></tr></thead>
    <tbody>
      <?php foreach ($memberships as $m): ?>
        <tr>
          <td><?= h($m['course_title']) ?> (#<?= h($m['course_id']) ?>)</td>
          <td><?= h($m['role_in_course']) ?></td>
          <td><a href="/courses/<?= h($m['course_id']) ?>">Open</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
