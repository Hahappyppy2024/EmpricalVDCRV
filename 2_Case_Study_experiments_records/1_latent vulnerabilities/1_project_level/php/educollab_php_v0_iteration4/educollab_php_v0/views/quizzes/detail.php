<div class="card">
    <h2><?= h($quiz['title']) ?></h2>
    <p><?= h($quiz['description']) ?></p>
    <p class="muted">open_at <?= h($quiz['open_at'] ?? '') ?> | due_at <?= h($quiz['due_at'] ?? '') ?></p>

    <div style="margin:12px 0">
        <a href="/courses/<?= h($course_id) ?>/quizzes/<?= h($quiz['quiz_id']) ?>/edit">Edit</a>
        <form style="display:inline" method="post" action="/courses/<?= h($course_id) ?>/quizzes/<?= h($quiz['quiz_id']) ?>/delete">
            <?= csrf_input() ?>
            <button type="submit">Delete</button>
        </form>
        <a href="/courses/<?= h($course_id) ?>/quizzes/<?= h($quiz['quiz_id']) ?>/questions">Configure Questions</a>
        <form style="display:inline" method="post" action="/courses/<?= h($course_id) ?>/quizzes/<?= h($quiz['quiz_id']) ?>/start">
            <?= csrf_input() ?>
            <button type="submit">Start Attempt</button>
        </form>
    </div>

    <h3>Attached Questions</h3>
    <?php if (empty($questions)): ?>
        <p class="muted">No questions attached.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($questions as $q): ?>
                <li>#<?= h((string)$q['question_id']) ?> - <?= h(snippet($q['prompt'])) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>