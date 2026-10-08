<?php
/** @var array<string, mixed> $quiz */
/** @var array<string, mixed> $course */
?>
<h1>Take Quiz: <?= htmlspecialchars($quiz['title']) ?></h1>
<p class="muted"><?= htmlspecialchars($quiz['description'] ?? '') ?> &middot; <?= (int) $quiz['time_limit_minutes'] ?> minute time limit. Answer all questions and submit.</p>

<div id="quiz-container"><div class="empty">Loading questions...</div></div>

<script>
(function () {
  var quizId = <?= (int) $quiz['id'] ?>;
  var cid = <?= (int) $course['id'] ?>;
  var container = document.getElementById('quiz-container');

  function render(data) {
    var questions = data.questions || [];
    if (!questions.length) { container.innerHTML = '<div class="empty">This quiz has no questions yet.</div>'; return; }
    var html = '<form id="attempt-form">';
    html += questions.map(function (question) {
      var options = question.options_json;
      try { options = typeof options === 'string' ? JSON.parse(options) : options; } catch (e) { options = []; }
      var choices = (Array.isArray(options) ? options : []).map(function (opt) {
        return '<label class="choice"><input type="radio" name="answers[' + question.id + ']" value="' + LMS.esc(opt) + '"> ' + LMS.esc(opt) + '</label>';
      }).join('');
      return '<div class="panel"><h3>' + LMS.esc(question.prompt) + '</h3><div>' + (choices || '<p class="muted">Short answer</p>') + '</div></div>';
    }).join('');
    html += '<button type="submit" class="btn btn-primary">Submit quiz</button></form>';
    container.innerHTML = html;

    document.getElementById('attempt-form').addEventListener('submit', function (e) {
      e.preventDefault();
      if (!confirm('Submit your answers?')) return;
      var answers = {};
      document.querySelectorAll('#attempt-form input[type=radio]:checked').forEach(function (input) {
        var m = input.name.match(/answers\[(\d+)\]/);
        if (m) answers[m[1]] = input.value;
      });
      LMS.api('/api/lms/quiz_lifecycle', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'attempt', quiz_id: quizId, answers: answers }) }).then(function (r) {
        if (r.ok) {
          container.innerHTML = '<div class="alert alert-success">Quiz submitted. Your score: ' + r.data.score + ' / ' + r.data.max_score + '</div>' +
            '<a class="btn btn-primary" href="/quizzes/' + quizId + '/results">View results</a>';
        } else {
          alert(r.error.message);
        }
      });
    });
  }

  LMS.load(container, LMS.api('/api/lms/quiz_lifecycle?course_id=' + cid + '&quiz_id=' + quizId), render);
})();
</script>
