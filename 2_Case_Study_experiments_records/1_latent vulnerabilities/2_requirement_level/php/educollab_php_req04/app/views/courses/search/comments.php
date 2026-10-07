<h1>Search Comments</h1>
<p><strong>Course:</strong> <?= htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8') ?></p>
<form method="get" action="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/search/comments">
    <label>Keyword</label>
    <input type="text" name="keyword" value="<?= htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') ?>">
    <button type="submit">Search comments</button>
</form>
<?php foreach ($results as $comment): ?>
    <div class="card">
        <p><strong>Post:</strong> <a href="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/posts/<?= htmlspecialchars((string) $comment['post_id'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($comment['post_title'], ENT_QUOTES, 'UTF-8') ?></a></p>
        <p><?= nl2br(htmlspecialchars($comment['body'], ENT_QUOTES, 'UTF-8')) ?></p>
    </div>
<?php endforeach; ?>
<?php if ($keyword !== '' && !$results): ?><p>No matching comments.</p><?php endif; ?>
