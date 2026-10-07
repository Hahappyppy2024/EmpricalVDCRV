<h1>Upload File</h1>
<?php if (!empty($course)): ?>
    <p><strong>Course:</strong> <?= htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<?php if (!empty($error)): ?><p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<p>Allowed file types: .txt, .md, .csv, .pdf, .png, .jpg, .jpeg, .gif (max 10 MB)</p>
<form method="post" action="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/uploads" enctype="multipart/form-data">
    <label>File</label>
    <input type="file" name="file" accept=".txt,.md,.csv,.pdf,.png,.jpg,.jpeg,.gif" required>
    <button type="submit">Upload file</button>
</form>