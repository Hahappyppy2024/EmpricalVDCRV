<?php if (!$post): ?>
    <h1>Post Not Found</h1>
    <p>The requested post does not exist.</p>
<?php else: ?>
    <h1><?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?></h1>
    <p><a href="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/posts">Back to posts</a></p>
    <div class="card">
        <p><strong>Author:</strong> <?= htmlspecialchars($post['author_display_name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($post['author_username'], ENT_QUOTES, 'UTF-8') ?>)</p>
        <p><strong>Created At:</strong> <?= htmlspecialchars($post['created_at'], ENT_QUOTES, 'UTF-8') ?></p>
        <p><strong>Updated At:</strong> <?= htmlspecialchars($post['updated_at'], ENT_QUOTES, 'UTF-8') ?></p>
        <p><?= nl2br(htmlspecialchars($post['body'], ENT_QUOTES, 'UTF-8')) ?></p>
    </div>

    <?php if (!empty($can_manage_post)): ?>
        <p><a href="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/posts/<?= htmlspecialchars((string) $post['post_id'], ENT_QUOTES, 'UTF-8') ?>/edit">Edit post</a></p>
        <form method="post" action="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/posts/<?= htmlspecialchars((string) $post['post_id'], ENT_QUOTES, 'UTF-8') ?>/delete">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit">Delete post</button>
        </form>
    <?php endif; ?>

    <h2>Comments</h2>
    <?php if (!empty($comment_error)): ?>
        <p class="error"><?= htmlspecialchars($comment_error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <form method="post" action="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/posts/<?= htmlspecialchars((string) $post['post_id'], ENT_QUOTES, 'UTF-8') ?>/comments">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
        <label>Add comment</label>
        <textarea name="body" rows="4" required></textarea>
        <button type="submit">Post comment</button>
    </form>

    <?php if (!$comments): ?>
        <p>No comments yet.</p>
    <?php endif; ?>

    <?php foreach ($comments as $comment): ?>
        <div class="card">
            <p><strong>Author:</strong> <?= htmlspecialchars($comment['author_display_name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($comment['author_username'], ENT_QUOTES, 'UTF-8') ?>)</p>
            <p><strong>Created At:</strong> <?= htmlspecialchars($comment['created_at'], ENT_QUOTES, 'UTF-8') ?></p>
            <p><strong>Updated At:</strong> <?= htmlspecialchars($comment['updated_at'], ENT_QUOTES, 'UTF-8') ?></p>
            <p><?= nl2br(htmlspecialchars($comment['body'], ENT_QUOTES, 'UTF-8')) ?></p>

            <?php if (!empty($comment['can_manage'])): ?>
                <form method="post" action="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/posts/<?= htmlspecialchars((string) $post['post_id'], ENT_QUOTES, 'UTF-8') ?>/comments/<?= htmlspecialchars((string) $comment['comment_id'], ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <label>Edit comment</label>
                    <textarea name="body" rows="3" required><?= htmlspecialchars($comment['body'], ENT_QUOTES, 'UTF-8') ?></textarea>
                    <button type="submit">Save comment</button>
                </form>

                <form method="post" action="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/posts/<?= htmlspecialchars((string) $post['post_id'], ENT_QUOTES, 'UTF-8') ?>/comments/<?= htmlspecialchars((string) $comment['comment_id'], ENT_QUOTES, 'UTF-8') ?>/delete">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit">Delete comment</button>
                </form>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>