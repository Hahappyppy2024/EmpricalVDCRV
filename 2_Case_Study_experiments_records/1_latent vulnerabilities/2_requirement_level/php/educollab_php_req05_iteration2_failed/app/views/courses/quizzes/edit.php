<h1>Edit Quiz</h1>
<p><strong>Course:</strong> <?= htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8') ?></p>
<?php if (!empty($error)): ?><p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<form method="post" action="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/quizzes/<?= htmlspecialchars((string) $quiz['quiz_id'], ENT_QUOTES, 'UTF-8') ?>">
    <label>Title</label>
    <input type="text" name="title" value="<?= htmlspecialchars($quiz['title'], ENT_QUOTES, 'UTF-8') ?>" required>
    <label>Description</label>
    <textarea name="description" rows="5"><?= htmlspecialchars($quiz['description'], ENT_QUOTES, 'UTF-8') ?></textarea>
    <label>Open At</label>
    <input type="text" name="open_at" value="<?= htmlspecialchars((string) ($quiz['open_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
    <label>Due At</label>
    <input type="text" name="due_at" value="<?= htmlspecialchars((string) ($quiz['due_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
    <button type="submit">Save quiz</button>
</form>
