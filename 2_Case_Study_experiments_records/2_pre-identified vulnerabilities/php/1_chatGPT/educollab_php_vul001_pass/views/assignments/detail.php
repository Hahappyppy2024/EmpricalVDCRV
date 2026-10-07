<div class="card">
  <h2><?= h($assignment['title']) ?></h2>
  <p><?= h($assignment['description']) ?></p>
  <p class="muted">due <?= h($assignment['due_at'] ?? '') ?> | created_by <?= h($assignment['creator_username']) ?></p>

  <div>
    <a href="/courses/<?= h($course_id) ?>/assignments/<?= h($assignment['assignment_id']) ?>/edit">Edit (staff)</a>
    <form style="display:inline" method="post" action="/courses/<?= h($course_id) ?>/assignments/<?= h($assignment['assignment_id']) ?>/delete"><button type="submit">Delete (staff)</button></form>
  </div>

  <hr />
  <h3>Student actions</h3>
  <a href="/courses/<?= h($course_id) ?>/assignments/<?= h($assignment['assignment_id']) ?>/submit">Submit / Update my submission (student)</a>

  <hr />
  <h3>Staff actions</h3>
  <a href="/courses/<?= h($course_id) ?>/assignments/<?= h($assignment['assignment_id']) ?>/submissions">View submissions (staff)</a>
</div>
