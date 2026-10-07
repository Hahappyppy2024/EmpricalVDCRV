<div class="card">
    <h2>Question #<?= h($question['question_id']) ?></h2>
    <p><b>Type:</b> <?= h($question['qtype']) ?></p>
    <p><b>Prompt:</b></p>
    <div style="white-space:pre-wrap"><?= h($question['prompt']) ?></div>
    <p><b>Options JSON:</b> <pre><?= h($question['options_json'] ?? '') ?></pre></p>
    <p><b>Answer JSON:</b> <pre><?= h($question['answer_json'] ?? '') ?></pre></p>

    <div style="margin-top:12px">
        <a href="/courses/<?= h($course_id) ?>/questions/<?= h($question['question_id']) ?>/edit">Edit</a>
        <form style="display:inline" method="post" action="/courses/<?= h($course_id) ?>/questions/<?= h($question['question_id']) ?>/delete">
            <?= csrf_input() ?>
            <button type="submit">Delete</button>
        </form>
    </div>
</div>