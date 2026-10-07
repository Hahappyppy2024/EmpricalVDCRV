<h1>Assignments</h1>
<p><strong>Course:</strong> <?= htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8') ?></p>
<?php if ($is_staff): ?><p><a href="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/assignments/new">New assignment</a></p><?php endif; ?>
<?php if (!$assignments): ?><p>No assignments yet.</p><?php endif; ?>
<?php foreach ($assignments as $assignment): ?>
<div class="card">
    <h2><a href="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/assignments/<?= htmlspecialchars((string) $assignment['assignment_id'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($assignment['title'], ENT_QUOTES, 'UTF-8') ?></a></h2>
    <p><?= nl2br(htmlspecialchars($assignment['description'], ENT_QUOTES, 'UTF-8')) ?></p>
    <p>Due: <?= htmlspecialchars((string) ($assignment['due_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
</div>
<?php endforeach; ?>
