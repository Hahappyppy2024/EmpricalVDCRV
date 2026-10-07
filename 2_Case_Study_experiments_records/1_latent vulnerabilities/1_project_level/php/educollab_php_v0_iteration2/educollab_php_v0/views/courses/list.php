<div class="card">
  <h2>Courses</h2>
  <a href="/courses/new">Create course</a>
  <ul>
    <?php foreach ($courses as $c): ?>
      <li>
        <a href="/courses/<?= h($c['course_id']) ?>"><?= h($c['title']) ?></a>
        <span class="muted">(created by <?= h($c['creator_username']) ?>)</span>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
