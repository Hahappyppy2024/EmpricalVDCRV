<h1>Edit Assignment</h1>
<p><strong>Course:</strong> <?= htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8') ?></p>
<?php if (!empty($error)): ?><p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<form method="post" action="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/assignments/<?= htmlspecialchars((string) $assignment['assignment_id'], ENT_QUOTES, 'UTF-8') ?>">
    <label>Title</label><input type="text" name="title" value="<?= htmlspecialchars($assignment['title'], ENT_QUOTES, 'UTF-8') ?>" required>
    <label>Description</label><textarea name="description" rows="6"><?= htmlspecialchars($assignment['description'], ENT_QUOTES, 'UTF-8') ?></textarea>
    <label>Due At</label><input type="text" name="due_at" value="<?= htmlspecialchars((string) ($assignment['due_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
    <button type="submit">Save assignment</button>
</form>
