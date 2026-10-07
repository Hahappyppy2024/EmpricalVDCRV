<h1>Comments</h1>
<?php if (!$post): ?>
    <p>Post not found.</p>
<?php else: ?>
    <p><strong>Course:</strong> <?= htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8') ?></p>
    <p><strong>Post:</strong> <a href="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/posts/<?= htmlspecialchars((string) $post['post_id'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?></a></p>
    <?php if (!$comments): ?><p>No comments yet.</p><?php endif; ?>
    <?php foreach ($comments as $comment): ?>
        <div class="card">
            <p><?= nl2br(htmlspecialchars($comment['body'], ENT_QUOTES, 'UTF-8')) ?></p>
            <p>By <?= htmlspecialchars($comment['author_display_name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($comment['author_username'], ENT_QUOTES, 'UTF-8') ?>)</p>
            <p>Updated: <?= htmlspecialchars($comment['updated_at'], ENT_QUOTES, 'UTF-8') ?></p>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
