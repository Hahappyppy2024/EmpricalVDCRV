<div class="card">
    <h2>Edit Assignment</h2>
    <form method="post" action="/courses/<?= h($course_id) ?>/assignments/<?= h($assignment['assignment_id']) ?>">
        <?= csrf_input() ?>
        <label>Title</label>
        <input name="title" value="<?= h($assignment['title']) ?>" />
        <label>Description</label>
        <textarea name="description"><?= h($assignment['description']) ?></textarea>
        <label>Due At (ISO)</label>
        <input name="due_at" value="<?= h($assignment['due_at'] ?? '') ?>" />
        <div style="margin-top:12px"><button type="submit">Save</button></div>
    </form>
</div>