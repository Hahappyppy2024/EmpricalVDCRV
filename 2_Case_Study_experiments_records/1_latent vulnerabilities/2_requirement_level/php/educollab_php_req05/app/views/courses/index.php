<h1>Courses</h1>
<?php if (!$courses): ?>
    <p>No courses yet.</p>
<?php endif; ?>
<?php foreach ($courses as $course): ?>
    <div class="card">
        <h2><a href="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8') ?></a></h2>
        <p><?= nl2br(htmlspecialchars($course['description'], ENT_QUOTES, 'UTF-8')) ?></p>
        <p>Created by <?= htmlspecialchars($course['creator_display_name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($course['creator_username'], ENT_QUOTES, 'UTF-8') ?>)</p>
    </div>
<?php endforeach; ?>
