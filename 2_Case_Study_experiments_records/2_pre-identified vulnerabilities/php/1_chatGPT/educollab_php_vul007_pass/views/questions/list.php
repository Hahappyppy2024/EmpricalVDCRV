<div class="card">
  <h2>Question Bank (course <?= h($course_id) ?>)</h2>
  <a href="/courses/<?= h($course_id) ?>/questions/new">New question (staff)</a>
  <ul>
    <?php foreach ($questions as $q): ?>
      <li><a href="/courses/<?= h($course_id) ?>/questions/<?= h($q['question_id']) ?>">#<?= h($q['question_id']) ?></a> <?= h($q['qtype']) ?> - <?= h(snippet($q['prompt'], 80)) ?></li>
    <?php endforeach; ?>
  </ul>
</div>
