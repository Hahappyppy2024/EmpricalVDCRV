<div class="card">
  <h2>Search Posts (course <?= h($course_id) ?>)</h2>
  <form method="get" action="/courses/<?= h($course_id) ?>/search">
    <input name="keyword" value="<?= h($keyword) ?>" placeholder="keyword" />
    <button type="submit">Search</button>
  </form>
  <ul>
    <?php foreach ($posts as $p): ?>
      <li><a href="/courses/<?= h($course_id) ?>/posts/<?= h($p['post_id']) ?>"><?= h($p['title']) ?></a></li>
    <?php endforeach; ?>
  </ul>
</div>
