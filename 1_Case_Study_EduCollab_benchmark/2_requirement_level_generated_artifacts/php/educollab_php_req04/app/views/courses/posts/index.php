<h1>Posts</h1>
<p><strong>Course:</strong> <?= htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8') ?></p>
<p>
    <a href="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/posts/new">New post</a> |
    <a href="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/search?keyword=">Search posts</a> |
    <a href="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/search/comments?keyword=">Search comments</a>
</p>
<?php if (!$posts): ?><p>No posts yet.</p><?php endif; ?>
<?php foreach ($posts as $post): ?>
<div class="card">
    <h2><a href="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/posts/<?= htmlspecialchars((string) $post['post_id'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?></a></h2>
    <p><?= nl2br(htmlspecialchars($post['body'], ENT_QUOTES, 'UTF-8')) ?></p>
    <p>By <?= htmlspecialchars($post['author_display_name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($post['author_username'], ENT_QUOTES, 'UTF-8') ?>)</p>
</div>
<?php endforeach; ?>
