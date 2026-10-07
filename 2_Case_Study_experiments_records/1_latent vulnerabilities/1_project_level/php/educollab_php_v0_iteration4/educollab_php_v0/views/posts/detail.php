<div class="card">
    <h2><?= h($post['title']) ?></h2>
    <p class="muted">by <?= h($post['author_username']) ?> | updated <?= h($post['updated_at']) ?></p>
    <div style="white-space:pre-wrap"><?= h($post['body']) ?></div>

    <div style="margin:12px 0">
        <a href="/courses/<?= h($course_id) ?>/posts/<?= h($post['post_id']) ?>/edit">Edit</a>
        <form style="display:inline" method="post" action="/courses/<?= h($course_id) ?>/posts/<?= h($post['post_id']) ?>/delete">
            <?= csrf_input() ?>
            <button type="submit">Delete</button>
        </form>
    </div>

    <h3>Comments</h3>
    <?php if (empty($comments)): ?>
        <p class="muted">No comments yet.</p>
    <?php else: ?>
        <?php foreach ($comments as $c): ?>
            <div class="card" style="margin:10px 0">
                <p class="muted">#<?= h((string)$c['comment_id']) ?> by <?= h($c['author_username']) ?> | <?= h($c['updated_at']) ?></p>
                <form method="post" action="/courses/<?= h($course_id) ?>/posts/<?= h($post['post_id']) ?>/comments/<?= h($c['comment_id']) ?>">
                    <?= csrf_input() ?>
                    <textarea name="body"><?= h($c['body']) ?></textarea>
                    <div style="margin-top:8px">
                        <button type="submit">Update Comment</button>
                    </div>
                </form>
                <form method="post" action="/courses/<?= h($course_id) ?>/posts/<?= h($post['post_id']) ?>/comments/<?= h($c['comment_id']) ?>/delete" style="margin-top:8px">
                    <?= csrf_input() ?>
                    <button type="submit">Delete Comment</button>
                </form>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <h3>Add Comment</h3>
    <form method="post" action="/courses/<?= h($course_id) ?>/posts/<?= h($post['post_id']) ?>/comments">
        <?= csrf_input() ?>
        <textarea name="body"></textarea>
        <div style="margin-top:8px"><button type="submit">Add Comment</button></div>
    </form>
</div>