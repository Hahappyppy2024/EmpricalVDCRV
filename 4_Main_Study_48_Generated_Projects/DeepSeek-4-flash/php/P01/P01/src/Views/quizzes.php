<?php
/** @var array<string, mixed> $course */
/** @var array<string, mixed>|null $user */
$role = $user['role_name'];
$isManager = $role === 'admin' || ($role === 'instructor' && (int) $course['instructor_id'] === (int) $user['id']);
?>
<nav class="tabs">
  <a href="/courses/<?= (int) $course['id'] ?>">Materials &amp; Announcements</a>
  <a href="/courses/<?= (int) $course['id'] ?>/discussion">Discussion</a>
  <a href="/courses/<?= (int) $course['id'] ?>/assignments">Assignments</a>
  <a href="/courses/<?= (int) $course['id'] ?>/quizzes" class="active">Quizzes</a>
  <?php if ($isManager): ?><a href="/courses/<?= (int) $course['id'] ?>/gradebook">Gradebook</a><?php endif; ?>
</nav>
<h1>Quizzes &mdash; <?= htmlspecialchars($course['title']) ?></h1>

<?php if ($isManager): ?>
<details class="panel">
  <summary>Create quiz</summary>
  <form id="quiz-form">
    <input type="hidden" name="action" value="quiz">
    <input type="hidden" name="course_id" value="<?= (int) $course['id'] ?>">
    <label>Title <input type="text" name="title" class="input" required></label>
    <label>Description <textarea name="description" class="input"></textarea></label>
    <label>Time limit (minutes) <input type="number" name="time_limit_minutes" value="10" class="input" required></label>
    <button type="submit" class="btn btn-primary">Create</button>
  </form>
</details>
<?php endif; ?>

<div id="quizzes"><div class="empty">Loading...</div></div>

<script>
(function () {
  var cid = <?= (int) $course['id'] ?>;
  var isManager = <?= $isManager ? 'true' : 'false' ?>;
  var container = document.getElementById('quizzes');

  function render(data) {
    var list = data.quizzes || [];
    if (!list.length) { container.innerHTML = '<div class="empty">No quizzes yet.</div>'; return; }
    container.innerHTML = list.map(function (q) {
      var action;
      if (isManager) {
        action = '<a class="btn" href="/courses/' + cid + '/gradebook">Manage</a>';
      } else if (q.my_attempt && q.my_attempt.status === 'completed') {
        action = '<a class="btn" href="/quizzes/' + q.id + '/results">View results (' + q.my_attempt.score + '/' + q.question_count + ')</a>';
      } else {
        action = '<a class="btn btn-primary" href="/quizzes/' + q.id + '/take">Take quiz</a>';
      }
      return '<div class="panel"><h3>' + LMS.esc(q.title) + '</h3><p>' + LMS.esc(q.description || '') + '</p>' +
        '<p class="muted">' + q.question_count + ' questions &middot; ' + q.time_limit_minutes + ' min limit &middot; ' + LMS.esc(q.instructor_name) + '</p>' + action + '</div>';
    }).join('');
  }

  function load() {
    LMS.load(container, LMS.api('/api/lms/quiz_lifecycle?course_id=' + cid), render);
  }
  load();

  var qf = document.getElementById('quiz-form');
  if (qf) qf.addEventListener('submit', function (e) {
    e.preventDefault();
    LMS.api('/api/lms/quiz_lifecycle', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(Object.fromEntries(new FormData(qf))) }).then(function (r) {
      if (r.ok) { qf.reset(); load(); } else { alert(r.error.message); }
    });
  });
})();
</script>
