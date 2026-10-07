<div class="card">
  <h2>Search Comments (course <?= h($course_id) ?>)</h2>
  <form method="get" action="/courses/<?= h($course_id) ?>/search/comments">
    <input name="keyword" value="<?= h($keyword) ?>" placeholder="keyword" />
    <button type="submit">Search</button>
  </form>
  <ul>
    <?php foreach ($comments as $c): ?>
      <li>
        #<?= h($c['comment_id']) ?> in post <?= h($c['post_id']) ?>: <?= h($c['body']) ?>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
