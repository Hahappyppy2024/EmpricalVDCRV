<h1>Upload File</h1>
<p><strong>Course:</strong> <?= htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8') ?></p>
<?php if (!empty($error)): ?><p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<form method="post" action="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/uploads" enctype="multipart/form-data">
    <label>File</label>
    <input type="file" name="file" required>
    <button type="submit">Upload file</button>
</form>
