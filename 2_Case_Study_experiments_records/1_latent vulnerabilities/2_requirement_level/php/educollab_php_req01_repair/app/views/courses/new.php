<h1>Create Course</h1>
<?php if (!empty($error)): ?>
    <p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<form method="post" action="/courses">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
    <label>Title</label>
    <input type="text" name="title" required>
    <label>Description</label>
    <textarea name="description" rows="5"></textarea>
    <button type="submit">Create course</button>
</form>