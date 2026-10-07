<?php if (!$assignment): ?>
    <h1>Assignment Not Found</h1>
<?php else: ?>
    <h1><?= htmlspecialchars($assignment['title'], ENT_QUOTES, 'UTF-8') ?></h1>
    <p><strong>Course:</strong> <?= htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8') ?></p>
    <div class="card">
        <p><?= nl2br(htmlspecialchars($assignment['description'], ENT_QUOTES, 'UTF-8')) ?></p>
        <p>Due: <?= htmlspecialchars((string) ($assignment['due_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
        <p>Created by <?= htmlspecialchars($assignment['creator_display_name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($assignment['creator_username'], ENT_QUOTES, 'UTF-8') ?>)</p>
    </div>
    <p><a href="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/assignments/<?= htmlspecialchars((string) $assignment['assignment_id'], ENT_QUOTES, 'UTF-8') ?>/submit">Submit assignment</a></p>
    <?php if ($is_staff): ?>
        <p><a href="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/assignments/<?= htmlspecialchars((string) $assignment['assignment_id'], ENT_QUOTES, 'UTF-8') ?>/edit">Edit assignment</a></p>
        <p><a href="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/assignments/<?= htmlspecialchars((string) $assignment['assignment_id'], ENT_QUOTES, 'UTF-8') ?>/submissions">View submissions</a></p>
        <form method="post" action="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/assignments/<?= htmlspecialchars((string) $assignment['assignment_id'], ENT_QUOTES, 'UTF-8') ?>/delete">
            <button type="submit">Delete assignment</button>
        </form>
        <h2>Staff submissions view</h2>
        <?php if (!$submissions): ?><p>No submissions yet.</p><?php endif; ?>
        <?php foreach ($submissions as $submission): ?>
            <div class="card">
                <p><strong>Student:</strong> <?= htmlspecialchars($submission['student_display_name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($submission['student_username'], ENT_QUOTES, 'UTF-8') ?>)</p>
                <p><?= nl2br(htmlspecialchars($submission['content_text'], ENT_QUOTES, 'UTF-8')) ?></p>
                <p><strong>Attachment:</strong> <?= htmlspecialchars((string) ($submission['attachment_original_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
<?php endif; ?>
