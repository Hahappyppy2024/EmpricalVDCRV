<?php if (!$post): ?>
    <h1>Post Not Found</h1>
<?php else: ?>
    <h1><?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?></h1>
    <p><strong>Course:</strong> <?= htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8') ?></p>
    <div class="card">
        <p><?= nl2br(htmlspecialchars($post['body'], ENT_QUOTES, 'UTF-8')) ?></p>
        <p>By <?= htmlspecialchars($post['author_display_name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($post['author_username'], ENT_QUOTES, 'UTF-8') ?>)</p>
        <p>Created: <?= htmlspecialchars($post['created_at'], ENT_QUOTES, 'UTF-8') ?></p>
        <p>Updated: <?= htmlspecialchars($post['updated_at'], ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <p>
        <a href="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/posts">Back to posts</a> |
        <a href="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/posts/<?= htmlspecialchars((string) $post['post_id'], ENT_QUOTES, 'UTF-8') ?>/edit">Edit post</a> |
        <a href="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/posts/<?= htmlspecialchars((string) $post['post_id'], ENT_QUOTES, 'UTF-8') ?>/comments">Comments only view</a>
    </p>
    <form method="post" action="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/posts/<?= htmlspecialchars((string) $post['post_id'], ENT_QUOTES, 'UTF-8') ?>/delete">
        <button type="submit">Delete post</button>
    </form>

    <h2>Add Comment</h2>
    <form method="post" action="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/posts/<?= htmlspecialchars((string) $post['post_id'], ENT_QUOTES, 'UTF-8') ?>/comments">
        <label>Comment body</label>
        <textarea name="body" rows="4" required></textarea>
        <button type="submit">Add comment</button>
    </form>

    <h2>Comments</h2>
    <?php if (!$comments): ?><p>No comments yet.</p><?php endif; ?>
    <?php foreach ($comments as $comment): ?>
        <div class="card">
            <p><?= nl2br(htmlspecialchars($comment['body'], ENT_QUOTES, 'UTF-8')) ?></p>
            <p>By <?= htmlspecialchars($comment['author_display_name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($comment['author_username'], ENT_QUOTES, 'UTF-8') ?>)</p>
            <form method="post" action="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/posts/<?= htmlspecialchars((string) $post['post_id'], ENT_QUOTES, 'UTF-8') ?>/comments/<?= htmlspecialchars((string) $comment['comment_id'], ENT_QUOTES, 'UTF-8') ?>">
                <label>Edit comment</label>
                <textarea name="body" rows="3" required><?= htmlspecialchars($comment['body'], ENT_QUOTES, 'UTF-8') ?></textarea>
                <button type="submit">Save comment</button>
            </form>
            <form method="post" action="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/posts/<?= htmlspecialchars((string) $post['post_id'], ENT_QUOTES, 'UTF-8') ?>/comments/<?= htmlspecialchars((string) $comment['comment_id'], ENT_QUOTES, 'UTF-8') ?>/delete">
                <button type="submit">Delete comment</button>
            </form>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
