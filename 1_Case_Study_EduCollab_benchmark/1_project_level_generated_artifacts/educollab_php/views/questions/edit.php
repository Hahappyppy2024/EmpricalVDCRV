<div class="card">
  <h2>Edit Question #<?= h($question['question_id']) ?></h2>
  <form method="post" action="/courses/<?= h($course_id) ?>/questions/<?= h($question['question_id']) ?>">
    <label>Type</label>
    <input name="qtype" value="<?= h($question['qtype']) ?>" />
    <label>Prompt</label>
    <textarea name="prompt" rows="4"><?= h($question['prompt']) ?></textarea>
    <label>Options JSON</label>
    <textarea name="options_json" rows="3"><?= h($question['options_json'] ?? '') ?></textarea>
    <label>Answer JSON</label>
    <textarea name="answer_json" rows="2"><?= h($question['answer_json'] ?? '') ?></textarea>
    <button type="submit">Save</button>
  </form>
</div>
