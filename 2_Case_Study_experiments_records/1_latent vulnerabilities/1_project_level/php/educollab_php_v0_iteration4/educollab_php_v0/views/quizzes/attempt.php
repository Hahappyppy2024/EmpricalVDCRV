<div class="card">
    <h2>Attempt #<?= h($attempt['attempt_id']) ?></h2>
    <p class="muted">quiz <?= h($quiz_id) ?> | started <?= h($attempt['started_at']) ?> | submitted <?= h($attempt['submitted_at'] ?? '') ?></p>

    <?php foreach ($quiz_questions as $q): ?>
        <div class="card" style="margin:10px 0">
            <p><b>Q<?= h((string)$q['question_id']) ?>:</b> <?= h($q['prompt']) ?></p>
            <form method="post" action="/courses/<?= h($course_id) ?>/quizzes/<?= h($quiz_id) ?>/attempts/<?= h($attempt['attempt_id']) ?>/answers">
                <?= csrf_input() ?>
                <input type="hidden" name="question_id" value="<?= h((string)$q['question_id']) ?>">
                <textarea name="answer_json"><?= h($answer_map[(int)$q['question_id']] ?? '') ?></textarea>
                <div style="margin-top:8px"><button type="submit">Save Answer</button></div>
            </form>
        </div>
    <?php endforeach; ?>

    <form method="post" action="/courses/<?= h($course_id) ?>/quizzes/<?= h($quiz_id) ?>/attempts/<?= h($attempt['attempt_id']) ?>/submit">
        <?= csrf_input() ?>
        <button type="submit">Submit Attempt</button>
    </form>
</div>