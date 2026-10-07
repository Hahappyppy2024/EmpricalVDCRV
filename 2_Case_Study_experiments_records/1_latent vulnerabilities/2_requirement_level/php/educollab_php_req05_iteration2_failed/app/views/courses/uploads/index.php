<h1>Uploads</h1>
<p><strong>Course:</strong> <?= htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8') ?></p>
<?php if ($is_staff): ?><p><a href="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/uploads/new">Upload file</a></p><?php endif; ?>
<?php if (!$uploads): ?><p>No uploads yet.</p><?php endif; ?>
<?php foreach ($uploads as $upload): ?>
<div class="card">
    <p><strong>Name:</strong> <?= htmlspecialchars($upload['original_name'], ENT_QUOTES, 'UTF-8') ?></p>
    <p><strong>Stored Path:</strong> <?= htmlspecialchars($upload['storage_path'], ENT_QUOTES, 'UTF-8') ?></p>
    <p><a href="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/uploads/<?= htmlspecialchars((string) $upload['upload_id'], ENT_QUOTES, 'UTF-8') ?>/download">Download</a></p>
    <?php if ($is_staff): ?>
        <form method="post" action="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/uploads/<?= htmlspecialchars((string) $upload['upload_id'], ENT_QUOTES, 'UTF-8') ?>/delete">
            <button type="submit">Delete upload</button>
        </form>
    <?php endif; ?>
</div>
<?php endforeach; ?>
