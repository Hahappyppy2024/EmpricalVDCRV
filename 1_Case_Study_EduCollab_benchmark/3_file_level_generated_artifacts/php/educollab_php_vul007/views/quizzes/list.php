<div class="card">
  <h2>Quizzes (course <?= h($course_id) ?>)</h2>
  <a href="/courses/<?= h($course_id) ?>/quizzes/new">New quiz (staff)</a>
  <ul>
    <?php foreach ($quizzes as $q): ?>
      <li><a href="/courses/<?= h($course_id) ?>/quizzes/<?= h($q['quiz_id']) ?>"><?= h($q['title']) ?></a></li>
    <?php endforeach; ?>
  </ul>
</div>
