<h1>Submissions</h1>
<p><strong>Course:</strong> <?= htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8') ?></p>
<p><strong>Assignment:</strong> <?= htmlspecialchars($assignment['title'], ENT_QUOTES, 'UTF-8') ?></p>
<?php if (!$submissions): ?><p>No submissions yet.</p><?php endif; ?>
<?php foreach ($submissions as $submission): ?>
<div class="card">
    <p><strong>Student:</strong> <?= htmlspecialchars($submission['student_display_name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($submission['student_username'], ENT_QUOTES, 'UTF-8') ?>)</p>
    <p><?= nl2br(htmlspecialchars($submission['content_text'], ENT_QUOTES, 'UTF-8')) ?></p>
    <p><strong>Attachment:</strong> <?= htmlspecialchars((string) ($submission['attachment_original_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
</div>
<?php endforeach; ?>
