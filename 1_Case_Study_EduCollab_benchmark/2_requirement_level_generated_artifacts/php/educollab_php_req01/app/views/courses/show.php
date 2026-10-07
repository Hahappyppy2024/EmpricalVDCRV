<?php if (!$course): ?>
    <h1>Course Not Found</h1>
    <p>The requested course does not exist.</p>
<?php else: ?>
    <h1><?= htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8') ?></h1>
    <div class="card">
        <p><strong>Course ID:</strong> <?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?></p>
        <p><strong>Description:</strong><br><?= nl2br(htmlspecialchars($course['description'], ENT_QUOTES, 'UTF-8')) ?></p>
        <p><strong>Created By:</strong> <?= htmlspecialchars($course['creator_display_name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($course['creator_username'], ENT_QUOTES, 'UTF-8') ?>)</p>
        <p><strong>Created At:</strong> <?= htmlspecialchars($course['created_at'], ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <a href="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/edit">Edit</a>
    <form method="post" action="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/delete">
        <button type="submit">Delete course</button>
    </form>
<?php endif; ?>
