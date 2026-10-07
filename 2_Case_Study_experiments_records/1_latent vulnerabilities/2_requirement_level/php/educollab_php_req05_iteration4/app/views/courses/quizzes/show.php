<?php if (!$quiz): ?>
<h1>Quiz Not Found</h1>
<?php else: ?>
<h1><?= htmlspecialchars($quiz['title'], ENT_QUOTES, 'UTF-8') ?></h1>
<p><strong>Course:</strong> <?= htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8') ?></p>
<div class="card">
    <p><?= nl2br(htmlspecialchars($quiz['description'], ENT_QUOTES, 'UTF-8')) ?></p>
    <p>Open: <?= htmlspecialchars((string) ($quiz['open_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
    <p>Due: <?= htmlspecialchars((string) ($quiz['due_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
</div>

<h2>Quiz Questions</h2>
<?php if (!$questions): ?><p>No questions configured.</p><?php endif; ?>
<?php foreach ($questions as $question): ?>
<div class="card">
    <p><strong>Q<?= htmlspecialchars((string) $question['question_id'], ENT_QUOTES, 'UTF-8') ?></strong> (<?= htmlspecialchars((string) $question['points'], ENT_QUOTES, 'UTF-8') ?> pts, pos <?= htmlspecialchars((string) $question['position'], ENT_QUOTES, 'UTF-8') ?>)</p>
    <p><?= nl2br(htmlspecialchars($question['prompt'], ENT_QUOTES, 'UTF-8')) ?></p>
    <p><strong>Type:</strong> <?= htmlspecialchars($question['qtype'], ENT_QUOTES, 'UTF-8') ?></p>
</div>
<?php endforeach; ?>

<?php if ($is_staff): ?>
<h2>Configure Quiz Questions</h2>
<form method="post" action="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/quizzes/<?= htmlspecialchars((string) $quiz['quiz_id'], ENT_QUOTES, 'UTF-8') ?>/questions">
    <label>Question</label>
    <select name="question_id" required>
        <option value="">Select question</option>
        <?php foreach ($question_bank as $qb): ?>
            <option value="<?= htmlspecialchars((string) $qb['question_id'], ENT_QUOTES, 'UTF-8') ?>">#<?= htmlspecialchars((string) $qb['question_id'], ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars($qb['prompt'], ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
    </select>
    <label>Points</label>
    <input type="number" name="points" value="1">
    <label>Position</label>
    <input type="number" name="position" value="1">
    <button type="submit">Add or update mapping</button>
</form>
<p>
    <a href="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/quizzes/<?= htmlspecialchars((string) $quiz['quiz_id'], ENT_QUOTES, 'UTF-8') ?>/edit">Edit quiz</a>
</p>
<form method="post" action="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/quizzes/<?= htmlspecialchars((string) $quiz['quiz_id'], ENT_QUOTES, 'UTF-8') ?>/delete">
    <button type="submit">Delete quiz</button>
</form>
<?php endif; ?>

<?php if ($is_student): ?>
<h2>My Attempts</h2>
<form method="post" action="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/quizzes/<?= htmlspecialchars((string) $quiz['quiz_id'], ENT_QUOTES, 'UTF-8') ?>/start">
    <button type="submit">Start Attempt</button>
</form>
<?php foreach ($attempts as $row): ?>
<div class="card">
    <p>Attempt #<?= htmlspecialchars((string) $row['attempt_id'], ENT_QUOTES, 'UTF-8') ?> | Started: <?= htmlspecialchars($row['started_at'], ENT_QUOTES, 'UTF-8') ?> | Submitted: <?= htmlspecialchars((string) ($row['submitted_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
    <?php if ($row['submitted_at'] === null): ?>
        <?php foreach ($questions as $question): ?>
        <form method="post" action="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/quizzes/<?= htmlspecialchars((string) $quiz['quiz_id'], ENT_QUOTES, 'UTF-8') ?>/attempts/<?= htmlspecialchars((string) $row['attempt_id'], ENT_QUOTES, 'UTF-8') ?>/answers">
            <input type="hidden" name="question_id" value="<?= htmlspecialchars((string) $question['question_id'], ENT_QUOTES, 'UTF-8') ?>">
            <label>Answer for Q<?= htmlspecialchars((string) $question['question_id'], ENT_QUOTES, 'UTF-8') ?></label>
            <textarea name="answer_json" rows="3" placeholder='{"answer":"..."}' required></textarea>
            <button type="submit">Save Answer</button>
        </form>
        <?php endforeach; ?>
        <form method="post" action="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/quizzes/<?= htmlspecialchars((string) $quiz['quiz_id'], ENT_QUOTES, 'UTF-8') ?>/attempts/<?= htmlspecialchars((string) $row['attempt_id'], ENT_QUOTES, 'UTF-8') ?>/submit">
            <button type="submit">Submit Attempt</button>
        </form>
    <?php endif; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>
<?php endif; ?>
