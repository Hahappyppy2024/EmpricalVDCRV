<div class="card">
    <h2>New Post</h2>
    <form method="post" action="/courses/<?= h($course_id) ?>/posts">
        <?= csrf_input() ?>
        <label>Title</label>
        <input name="title" />
        <label>Body</label>
        <textarea name="body"></textarea>
        <div style="margin-top:12px"><button type="submit">Create</button></div>
    </form>
</div>