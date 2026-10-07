<h1>My Quiz Attempts</h1>
<?php if (!$attempts): ?><p>No quiz attempts yet.</p><?php endif; ?>
<?php foreach ($attempts as $attempt): ?>
<div class="card">
    <p><strong>Course:</strong> <?= htmlspecialchars($attempt['course_title'], ENT_QUOTES, 'UTF-8') ?></p>
    <p><strong>Quiz:</strong> <?= htmlspecialchars($attempt['quiz_title'], ENT_QUOTES, 'UTF-8') ?></p>
    <p><strong>Attempt ID:</strong> <?= htmlspecialchars((string) $attempt['attempt_id'], ENT_QUOTES, 'UTF-8') ?></p>
    <p><strong>Started:</strong> <?= htmlspecialchars($attempt['started_at'], ENT_QUOTES, 'UTF-8') ?></p>
    <p><strong>Submitted:</strong> <?= htmlspecialchars((string) ($attempt['submitted_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
</div>
<?php endforeach; ?>
