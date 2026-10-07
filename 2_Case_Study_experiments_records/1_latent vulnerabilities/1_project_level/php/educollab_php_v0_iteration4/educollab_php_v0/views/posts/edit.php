<div class="card">
    <h2>Edit Post</h2>
    <form method="post" action="/courses/<?= h($course_id) ?>/posts/<?= h($post['post_id']) ?>">
        <?= csrf_input() ?>
        <label>Title</label>
        <input name="title" value="<?= h($post['title']) ?>" />
        <label>Body</label>
        <textarea name="body"><?= h($post['body']) ?></textarea>
        <div style="margin-top:12px"><button type="submit">Save</button></div>
    </form>
</div>