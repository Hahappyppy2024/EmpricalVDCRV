<h1>Edit Post</h1>
<p><strong>Course:</strong> <?= htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8') ?></p>
<?php if (!empty($error)): ?><p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<form method="post" action="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/posts/<?= htmlspecialchars((string) $post['post_id'], ENT_QUOTES, 'UTF-8') ?>">
    <label>Title</label>
    <input type="text" name="title" value="<?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>" required>
    <label>Body</label>
    <textarea name="body" rows="8" required><?= htmlspecialchars($post['body'], ENT_QUOTES, 'UTF-8') ?></textarea>
    <button type="submit">Save post</button>
</form>
