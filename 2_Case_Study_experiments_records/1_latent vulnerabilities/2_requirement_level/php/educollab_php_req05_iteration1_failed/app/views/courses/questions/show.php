<?php if (!$question): ?>
<h1>Question Not Found</h1>
<?php else: ?>
<h1>Question #<?= htmlspecialchars((string) $question['question_id'], ENT_QUOTES, 'UTF-8') ?></h1>
<p><strong>Course:</strong> <?= htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8') ?></p>
<div class="card">
    <p><strong>Type:</strong> <?= htmlspecialchars($question['qtype'], ENT_QUOTES, 'UTF-8') ?></p>
    <p><strong>Prompt:</strong><br><?= nl2br(htmlspecialchars($question['prompt'], ENT_QUOTES, 'UTF-8')) ?></p>
    <p><strong>Options JSON:</strong><br><code><?= htmlspecialchars((string) ($question['options_json'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code></p>
    <p><strong>Answer JSON:</strong><br><code><?= htmlspecialchars((string) ($question['answer_json'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code></p>
</div>
<p>
    <a href="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/questions">Back to questions</a> |
    <a href="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/questions/<?= htmlspecialchars((string) $question['question_id'], ENT_QUOTES, 'UTF-8') ?>/edit">Edit</a>
</p>
<form method="post" action="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/questions/<?= htmlspecialchars((string) $question['question_id'], ENT_QUOTES, 'UTF-8') ?>/delete">
    <button type="submit">Delete question</button>
</form>
<?php endif; ?>
