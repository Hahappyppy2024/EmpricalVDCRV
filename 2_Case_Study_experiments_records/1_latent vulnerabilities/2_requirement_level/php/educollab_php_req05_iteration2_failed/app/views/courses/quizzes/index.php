<h1>Quizzes</h1>
<p><strong>Course:</strong> <?= htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8') ?></p>
<?php if ($is_staff): ?><p><a href="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/quizzes/new">New quiz</a></p><?php endif; ?>
<?php if (!$quizzes): ?><p>No quizzes yet.</p><?php endif; ?>
<?php foreach ($quizzes as $quiz): ?>
<div class="card">
    <h2><a href="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/quizzes/<?= htmlspecialchars((string) $quiz['quiz_id'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($quiz['title'], ENT_QUOTES, 'UTF-8') ?></a></h2>
    <p><?= nl2br(htmlspecialchars($quiz['description'], ENT_QUOTES, 'UTF-8')) ?></p>
    <p>Open: <?= htmlspecialchars((string) ($quiz['open_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?> | Due: <?= htmlspecialchars((string) ($quiz['due_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
</div>
<?php endforeach; ?>
