<h1>My Submissions</h1>
<?php if (!$submissions): ?><p>No submissions yet.</p><?php endif; ?>
<?php foreach ($submissions as $submission): ?>
<div class="card">
    <p><strong>Course:</strong> <?= htmlspecialchars($submission['course_title'], ENT_QUOTES, 'UTF-8') ?></p>
    <p><strong>Assignment:</strong> <?= htmlspecialchars($submission['assignment_title'], ENT_QUOTES, 'UTF-8') ?></p>
    <p><?= nl2br(htmlspecialchars($submission['content_text'], ENT_QUOTES, 'UTF-8')) ?></p>
    <p><strong>Attachment:</strong> <?= htmlspecialchars((string) ($submission['attachment_original_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
    <p><strong>Updated At:</strong> <?= htmlspecialchars($submission['updated_at'], ENT_QUOTES, 'UTF-8') ?></p>
</div>
<?php endforeach; ?>
