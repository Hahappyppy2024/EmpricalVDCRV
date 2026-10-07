<div class="card">
    <h2>Configure Quiz Questions: <?= h($quiz['title']) ?></h2>
    <p class="muted">quiz_id=<?= h($quiz['quiz_id']) ?> | course <?= h($course_id) ?></p>

    <h3>Add question</h3>
    <form method="post" action="/courses/<?= h($course_id) ?>/quizzes/<?= h($quiz['quiz_id']) ?>/questions">
        <?= csrf_input() ?>
        <label>Question ID</label>
        <input type="number" name="question_id" />
        <div style="margin-top:8px"><button type="submit">Add</button></div>
    </form>

    <h3>Current quiz questions</h3>
    <?php if (empty($questions)): ?>
        <p class="muted">No questions attached yet.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>question_id</th><th>type</th><th>prompt</th><th>action</th></tr></thead>
            <tbody>
            <?php foreach ($questions as $q): ?>
                <tr>
                    <td><?= h((string)$q['question_id']) ?></td>
                    <td><?= h($q['qtype']) ?></td>
                    <td><?= h(snippet($q['prompt'])) ?></td>
                    <td>
                        <form method="post" action="/courses/<?= h($course_id) ?>/quizzes/<?= h($quiz['quiz_id']) ?>/questions/<?= h($q['question_id']) ?>/delete">
                            <?= csrf_input() ?>
                            <button type="submit">Remove</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>