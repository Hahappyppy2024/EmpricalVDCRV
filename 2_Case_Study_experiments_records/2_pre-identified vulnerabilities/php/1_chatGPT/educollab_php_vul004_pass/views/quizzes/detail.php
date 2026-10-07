<div class="card">
  <h2><?= h($quiz['title']) ?></h2>
  <p><?= h($quiz['description']) ?></p>
  <p class="muted">open_at <?= h($quiz['open_at'] ?? '') ?> | due_at <?= h($quiz['due_at'] ?? '') ?></p>

  <div>
    <a href="/courses/<?= h($course_id) ?>/quizzes/<?= h($quiz['quiz_id']) ?>/edit">Edit (staff)</a>
    <a href="/courses/<?= h($course_id) ?>/quizzes/<?= h($quiz['quiz_id']) ?>/questions">Configure questions (staff)</a>
    <form style="display:inline" method="post" action="/courses/<?= h($course_id) ?>/quizzes/<?= h($quiz['quiz_id']) ?>/delete"><button type="submit">Delete (staff)</button></form>
  </div>

  <hr />
  <h3>Questions in this quiz</h3>
  <ol>
    <?php foreach ($quiz_questions as $qq): ?>
      <li><?= h($qq['prompt']) ?> <span class="muted">(points <?= h($qq['points']) ?>)</span></li>
    <?php endforeach; ?>
  </ol>

  <hr />
  <h3>Student</h3>
  <form method="post" action="/courses/<?= h($course_id) ?>/quizzes/<?= h($quiz['quiz_id']) ?>/start">
    <button type="submit">Start attempt (student)</button>
  </form>
</div>
