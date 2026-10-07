<div class="card">
  <h2>Course: <?= h($course['title']) ?></h2>
  <p><?= h($course['description']) ?></p>
  <p class="muted">course_id=<?= h($course['course_id']) ?> | created_by=<?= h($course['creator_username']) ?></p>

  <div>
    <a href="/courses/<?= h($course['course_id']) ?>/edit">Edit</a>
    <form style="display:inline" method="post" action="/courses/<?= h($course['course_id']) ?>/delete"><button type="submit">Delete</button></form>
  </div>

  <hr />
  <h3>Modules</h3>
  <ul>
    <li><a href="/courses/<?= h($course['course_id']) ?>/members">Membership</a></li>
    <li><a href="/courses/<?= h($course['course_id']) ?>/posts">Posts</a> | <a href="/courses/<?= h($course['course_id']) ?>/search">Search Posts</a> | <a href="/courses/<?= h($course['course_id']) ?>/search/comments">Search Comments</a></li>
    <li><a href="/courses/<?= h($course['course_id']) ?>/assignments">Assignments</a></li>
    <li><a href="/courses/<?= h($course['course_id']) ?>/uploads">Uploads</a></li>
    <li><a href="/courses/<?= h($course['course_id']) ?>/materials">Materials</a></li>
    <li><a href="/courses/<?= h($course['course_id']) ?>/questions">Question Bank</a></li>
    <li><a href="/courses/<?= h($course['course_id']) ?>/quizzes">Quizzes</a></li>
  </ul>
</div>
