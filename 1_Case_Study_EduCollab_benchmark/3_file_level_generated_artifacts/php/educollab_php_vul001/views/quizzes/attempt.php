<div class="card">
  <h2>Attempt #<?= h($attempt['attempt_id']) ?></h2>
  <p class="muted">quiz <?= h($quiz_id) ?> | started <?= h($attempt['started_at']) ?> | submitted <?= h($attempt['submitted_at'] ?? '') ?></p>

  <ol>
    <?php foreach ($quiz_questions as $qq): ?>
      <li class="card">
        <div><b>Q<?= h($qq['position']) ?>:</b> <?= h($qq['prompt']) ?> <span class="muted">(points <?= h($qq['points']) ?>)</span></div>
        <form method="post" action="/courses/<?= h($course_id) ?>/quizzes/<?= h($quiz_id) ?>/attempts/<?= h($attempt['attempt_id']) ?>/answers">
          <input type="hidden" name="question_id" value="<?= h($qq['question_id']) ?>" />
          <label>Answer JSON</label>
          <textarea name="answer_json" rows="2"><?= h($answer_map[(int)$qq['question_id']] ?? '') ?></textarea>
          <button type="submit">Save Answer</button>
        </form>
      </li>
    <?php endforeach; ?>
  </ol>

  <form method="post" action="/courses/<?= h($course_id) ?>/quizzes/<?= h($quiz_id) ?>/attempts/<?= h($attempt['attempt_id']) ?>/submit">
    <button type="submit">Submit Attempt</button>
  </form>
</div>
