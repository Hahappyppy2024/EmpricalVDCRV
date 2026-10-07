<div class="card">
  <h2>Configure Quiz Questions: <?= h($quiz['title']) ?></h2>
  <p class="muted">quiz_id=<?= h($quiz['quiz_id']) ?> | course <?= h($course_id) ?></p>

  <h3>Add question</h3>
  <form method="post" action="/courses/<?= h($course_id) ?>/quizzes/<?= h($quiz['quiz_id']) ?>/questions">
    <label>Question</label>
    <select name="question_id">
      <?php foreach ($available as $q): ?>
        <option value="<?= h($q['question_id']) ?>">#<?= h($q['question_id']) ?> <?= h(snippet($q['prompt'], 80)) ?></option>
      <?php endforeach; ?>
    </select>
    <label>Points</label>
    <input name="points" value="1" />
    <label>Position</label>
    <input name="position" value="1" />
    <button type="submit">Add/Replace</button>
  </form>

  <hr />
  <h3>Current</h3>
  <table>
    <thead><tr><th>question_id</th><th>prompt</th><th>points</th><th>position</th><th>actions</th></tr></thead>
    <tbody>
      <?php foreach ($current as $qq): ?>
        <tr>
          <td><?= h($qq['question_id']) ?></td>
          <td><?= h(snippet($qq['prompt'], 80)) ?></td>
          <td><?= h($qq['points']) ?></td>
          <td><?= h($qq['position']) ?></td>
          <td>
            <form method="post" action="/courses/<?= h($course_id) ?>/quizzes/<?= h($quiz['quiz_id']) ?>/questions/<?= h($qq['question_id']) ?>/delete">
              <button type="submit">Remove</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
