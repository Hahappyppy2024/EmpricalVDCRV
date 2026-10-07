<h1>Search Posts</h1>
<p><strong>Course:</strong> <?= htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8') ?></p>
<form method="get" action="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/search">
    <label>Keyword</label>
    <input type="text" name="keyword" value="<?= htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') ?>">
    <button type="submit">Search posts</button>
</form>
<?php foreach ($results as $post): ?>
    <div class="card">
        <h2><a href="/courses/<?= htmlspecialchars((string) $course['course_id'], ENT_QUOTES, 'UTF-8') ?>/posts/<?= htmlspecialchars((string) $post['post_id'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?></a></h2>
        <p><?= nl2br(htmlspecialchars($post['body'], ENT_QUOTES, 'UTF-8')) ?></p>
    </div>
<?php endforeach; ?>
<?php if ($keyword !== '' && !$results): ?><p>No matching posts.</p><?php endif; ?>
