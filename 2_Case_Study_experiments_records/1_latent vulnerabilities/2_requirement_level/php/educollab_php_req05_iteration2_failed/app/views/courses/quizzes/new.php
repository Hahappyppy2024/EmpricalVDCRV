<h1>Create Quiz</h1>
<p><strong>Course:</strong> <?= htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8') ?></p>
<?php if (!empty($error)): ?><p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<form method="post" action="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/quizzes">
    <label>Title</label>
    <input type="text" name="title" required>
    <label>Description</label>
    <textarea name="description" rows="5"></textarea>
    <label>Open At</label>
    <input type="text" name="open_at" placeholder="2026-03-06T09:00:00">
    <label>Due At</label>
    <input type="text" name="due_at" placeholder="2026-03-07T23:59:00">
    <button type="submit">Create quiz</button>
</form>
