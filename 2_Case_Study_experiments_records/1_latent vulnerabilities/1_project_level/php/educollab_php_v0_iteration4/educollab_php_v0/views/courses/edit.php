<div class="card">
    <h2>Edit Course</h2>
    <form method="post" action="/courses/<?= h($course['course_id']) ?>">
        <?= csrf_input() ?>
        <label>Title</label>
        <input name="title" value="<?= h($course['title']) ?>" />
        <label>Description</label>
        <textarea name="description"><?= h($course['description']) ?></textarea>
        <div style="margin-top:12px"><button type="submit">Save</button></div>
    </form>
</div>