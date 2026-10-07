<h1>Edit Course</h1>
<?php if (!empty($error)): ?>
    <p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<form method="post" action="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
    <label>Title</label>
    <input type="text" name="title" value="<?= htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8') ?>" required>
    <label>Description</label>
    <textarea name="description" rows="5"><?= htmlspecialchars($course['description'], ENT_QUOTES, 'UTF-8') ?></textarea>
    <button type="submit">Save changes</button>
</form>