<h1>Create Post</h1>
<p><strong>Course:</strong> <?= htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8') ?></p>
<?php if (!empty($error)): ?><p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<form method="post" action="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/posts">
    <label>Title</label>
    <input type="text" name="title" required>
    <label>Body</label>
    <textarea name="body" rows="8" required></textarea>
    <button type="submit">Create post</button>
</form>
