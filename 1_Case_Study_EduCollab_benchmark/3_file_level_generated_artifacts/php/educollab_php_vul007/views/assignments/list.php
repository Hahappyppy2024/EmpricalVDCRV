<div class="card">
  <h2>Assignments (course <?= h($course_id) ?>)</h2>
  <a href="/courses/<?= h($course_id) ?>/assignments/new">New assignment (staff)</a>
  <ul>
    <?php foreach ($assignments as $a): ?>
      <li>
        <a href="/courses/<?= h($course_id) ?>/assignments/<?= h($a['assignment_id']) ?>"><?= h($a['title']) ?></a>
        <span class="muted">due <?= h($a['due_at'] ?? '') ?></span>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
