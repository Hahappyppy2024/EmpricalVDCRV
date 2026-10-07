<h1>Questions</h1>
<p><strong>Course:</strong> <?= htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8') ?></p>
<p><a href="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/questions/new">New question</a></p>
<?php if (!$questions): ?><p>No questions yet.</p><?php endif; ?>
<?php foreach ($questions as $question): ?>
<div class="card">
    <h2><a href="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/questions/<?= htmlspecialchars((string) $question['question_id'], ENT_QUOTES, 'UTF-8') ?>">Question #<?= htmlspecialchars((string) $question['question_id'], ENT_QUOTES, 'UTF-8') ?></a></h2>
    <p><strong>Type:</strong> <?= htmlspecialchars($question['qtype'], ENT_QUOTES, 'UTF-8') ?></p>
    <p><?= nl2br(htmlspecialchars($question['prompt'], ENT_QUOTES, 'UTF-8')) ?></p>
</div>
<?php endforeach; ?>
