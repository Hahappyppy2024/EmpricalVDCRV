<div class="card">
  <h2>Posts (course <?= h($course_id) ?>)</h2>
  <a href="/courses/<?= h($course_id) ?>/posts/new">New post</a>
  <ul>
    <?php foreach ($posts as $p): ?>
      <li>
        <a href="/courses/<?= h($course_id) ?>/posts/<?= h($p['post_id']) ?>"><?= h($p['title']) ?></a>
        <span class="muted">by <?= h($p['author_username']) ?></span>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
