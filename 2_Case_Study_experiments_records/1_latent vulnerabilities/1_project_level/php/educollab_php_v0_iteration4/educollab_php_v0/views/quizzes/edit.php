<div class="card">
    <h2>Edit Quiz</h2>
    <form method="post" action="/courses/<?= h($course_id) ?>/quizzes/<?= h($quiz['quiz_id']) ?>">
        <?= csrf_input() ?>
        <label>Title</label>
        <input name="title" value="<?= h($quiz['title']) ?>" />
        <label>Description</label>
        <textarea name="description"><?= h($quiz['description']) ?></textarea>
        <label>Open At (ISO)</label>
        <input name="open_at" value="<?= h($quiz['open_at'] ?? '') ?>" />
        <label>Due At (ISO)</label>
        <input name="due_at" value="<?= h($quiz['due_at'] ?? '') ?>" />
        <div style="margin-top:12px"><button type="submit">Save</button></div>
    </form>
</div>