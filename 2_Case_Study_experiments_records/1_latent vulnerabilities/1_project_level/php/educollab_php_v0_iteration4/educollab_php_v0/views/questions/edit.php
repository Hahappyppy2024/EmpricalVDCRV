<div class="card">
    <h2>Edit Question #<?= h($question['question_id']) ?></h2>
    <form method="post" action="/courses/<?= h($course_id) ?>/questions/<?= h($question['question_id']) ?>">
        <?= csrf_input() ?>
        <label>Type</label>
        <input name="qtype" value="<?= h($question['qtype']) ?>" />
        <label>Prompt</label>
        <textarea name="prompt"><?= h($question['prompt']) ?></textarea>
        <label>Options JSON</label>
        <textarea name="options_json"><?= h($question['options_json'] ?? '') ?></textarea>
        <label>Answer JSON</label>
        <textarea name="answer_json"><?= h($question['answer_json'] ?? '') ?></textarea>
        <div style="margin-top:12px"><button type="submit">Save</button></div>
    </form>
</div>