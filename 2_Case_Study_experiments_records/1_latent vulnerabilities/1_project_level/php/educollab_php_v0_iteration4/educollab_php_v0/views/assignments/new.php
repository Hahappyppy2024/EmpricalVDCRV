<div class="card">
    <h2>New Assignment</h2>
    <form method="post" action="/courses/<?= h($course_id) ?>/assignments">
        <?= csrf_input() ?>
        <label>Title</label>
        <input name="title" />
        <label>Description</label>
        <textarea name="description"></textarea>
        <label>Due At (ISO)</label>
        <input name="due_at" placeholder="2026-01-01T00:00:00Z" />
        <div style="margin-top:12px"><button type="submit">Create</button></div>
    </form>
</div>