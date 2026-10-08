<?php
/** @var array<string, mixed> $quiz */
/** @var array<string, mixed> $course */
/** @var array<string, mixed>|null $user */
$role = $user['role_name'];
$isManager = $role === 'admin' || ($role === 'instructor' && (int) $quiz['instructor_id'] === (int) $user['id']);
?>
<h1>Results: <?= htmlspecialchars($quiz['title']) ?></h1>
<div id="results"><div class="empty">Loading results...</div></div>

<script>
(function () {
  var quizId = <?= (int) $quiz['id'] ?>;
  var cid = <?= (int) $course['id'] ?>;
  var isManager = <?= $isManager ? 'true' : 'false' ?>;
  var container = document.getElementById('results');

  function render(data) {
    var html = '';
    if (isManager) {
      var quizzes = data.quizzes || [];
      var quiz = quizzes.find(function (q) { return q.id === quizId; }) || {};
      var attempts = quiz.attempts || [];
      if (!attempts.length) { container.innerHTML = '<div class="empty">No attempts recorded.</div>'; return; }
      html = '<table class="table"><thead><tr><th>Student</th><th>Score</th><th>Status</th><th>Submitted</th></tr></thead><tbody>' +
        attempts.map(function (a) {
          return '<tr><td>' + LMS.esc(a.display_name) + '</td><td>' + a.score + '</td><td>' + LMS.esc(a.status) + '</td><td>' + LMS.esc(a.submitted_at || '') + '</td></tr>';
        }).join('') + '</tbody></table>';
    } else {
      var quizzes = data.quizzes || [];
      var quiz = quizzes.find(function (q) { return q.id === quizId; }) || {};
      var attempt = quiz.my_attempt;
      if (!attempt) { container.innerHTML = '<div class="empty">No completed attempt found.</div>'; return; }
      html = '<div class="alert alert-success">Your score: <strong>' + attempt.score + '</strong> / ' + (quiz.question_count || 0) + '</div>' +
        '<p>Status: ' + LMS.esc(attempt.status) + ' &middot; Submitted: ' + LMS.esc(attempt.submitted_at || '') + '</p>';
    }
    html += '<p><a class="btn" href="/courses/' + cid + '/quizzes">&larr; Back to quizzes</a></p>';
    container.innerHTML = html;
  }

  LMS.load(container, LMS.api('/api/lms/quiz_lifecycle?course_id=' + cid), render);
})();
</script>
