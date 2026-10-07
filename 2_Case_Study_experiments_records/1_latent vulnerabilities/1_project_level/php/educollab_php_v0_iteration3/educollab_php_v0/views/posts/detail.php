<div class="card">
    <h2><?= h($post['title']) ?></h2>
    <div class="muted">#<?= h($post['post_id']) ?> by <?= h($post['author_username']) ?> | updated <?= h($post['updated_at']) ?></div>
    <div style="white-space:pre-wrap; margin-top:10px;"><?= h($post['body']) ?></div>

    <div style="margin-top:10px;">
        <a href="/courses/<?= h($course_id) ?>/posts/<?= h($post['post_id']) ?>/edit">Edit</a>
        <form style="display:inline" method="post" action="/courses/<?= h($course_id) ?>/posts/<?= h($post['post_id']) ?>/delete"><?= csrf_input() ?><button type="submit">Delete</button></form>
    </div>
</div>

<div class="card">
    <h3>Comments</h3>

    <form method="post" action="/courses/<?= h($course_id) ?>/posts/<?= h($post['post_id']) ?>/comments">
        <?= csrf_input() ?>
        <textarea name="body" rows="3" placeholder="Write a comment..."></textarea>
        <button type="submit">Add Comment</button>
    </form>

    <?php foreach ($comments as $c): ?>
        <div class="card" style="margin-top:10px;">
            <div class="muted">#<?= h($c['comment_id']) ?> by <?= h($c['author_username']) ?> | updated <?= h($c['updated_at']) ?></div>
            <div style="white-space:pre-wrap"><?= h($c['body']) ?></div>
            <form method="post" action="/courses/<?= h($course_id) ?>/posts/<?= h($post['post_id']) ?>/comments/<?= h($c['comment_id']) ?>">
                <?= csrf_input() ?>
                <textarea name="body" rows="2"><?= h($c['body']) ?></textarea>
                <button type="submit">Update</button>
            </form>
            <form method="post" action="/courses/<?= h($course_id) ?>/posts/<?= h($post['post_id']) ?>/comments/<?= h($c['comment_id']) ?>/delete">
                <?= csrf_input() ?>
                <button type="submit">Delete</button>
            </form>
        </div>
    <?php endforeach; ?>
</div>