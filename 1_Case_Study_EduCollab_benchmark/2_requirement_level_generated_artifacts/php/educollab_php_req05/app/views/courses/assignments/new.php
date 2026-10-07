<h1>Create Assignment</h1>
<p><strong>Course:</strong> <?= htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8') ?></p>
<?php if (!empty($error)): ?><p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<form method="post" action="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/assignments">
    <label>Title</label><input type="text" name="title" required>
    <label>Description</label><textarea name="description" rows="6"></textarea>
    <label>Due At</label><input type="text" name="due_at" placeholder="2026-03-06T23:59:00">
    <button type="submit">Create assignment</button>
</form>
