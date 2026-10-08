<?php
/** @var array<string, mixed> $course */
/** @var array<string, mixed>|null $user */
$role = $user['role_name'];
$isManager = $role === 'admin' || ($role === 'instructor' && (int) $course['instructor_id'] === (int) $user['id']);
?>
<nav class="tabs">
  <a href="/courses/<?= (int) $course['id'] ?>">Materials &amp; Announcements</a>
  <a href="/courses/<?= (int) $course['id'] ?>/discussion">Discussion</a>
  <a href="/courses/<?= (int) $course['id'] ?>/assignments" class="active">Assignments</a>
  <a href="/courses/<?= (int) $course['id'] ?>/quizzes">Quizzes</a>
  <?php if ($isManager): ?><a href="/courses/<?= (int) $course['id'] ?>/gradebook">Gradebook</a><?php endif; ?>
</nav>
<h1>Assignments &mdash; <?= htmlspecialchars($course['title']) ?></h1>

<?php if ($isManager): ?>
<details class="panel">
  <summary>Create assignment</summary>
  <form id="assignment-form">
    <input type="hidden" name="action" value="create">
    <input type="hidden" name="course_id" value="<?= (int) $course['id'] ?>">
    <label>Title <input type="text" name="title" class="input" required></label>
    <label>Description <textarea name="description" class="input"></textarea></label>
    <label>Due date <input type="datetime-local" name="due_at" class="input"></label>
    <label>Max points <input type="number" name="max_points" value="100" class="input" required></label>
    <button type="submit" class="btn btn-primary">Create</button>
  </form>
</details>
<?php endif; ?>

<div id="assignments"><div class="empty">Loading...</div></div>

<script>
(function () {
  var cid = <?= (int) $course['id'] ?>;
  var isManager = <?= $isManager ? 'true' : 'false' ?>;
  var container = document.getElementById('assignments');

  function render(data) {
    var list = data.assignments || [];
    if (!list.length) { container.innerHTML = '<div class="empty">No assignments yet.</div>'; return; }
    container.innerHTML = list.map(function (a) {
      var html = '<div class="panel"><h3>' + LMS.esc(a.title) + '</h3><p>' + LMS.esc(a.description || '') + '</p>' +
        '<p class="muted">Due: ' + LMS.esc(a.due_at || 'no due date') + ' &middot; ' + a.max_points + ' points &middot; ' + a.submission_count + ' submissions</p>';
      if (isManager) {
        html += '<div id="subs-' + a.id + '"><div class="empty">Loading submissions...</div></div>';
      } else {
        html += '<div id="mysub-' + a.id + '"></div>' +
          '<form class="submit-form" data-assignment="' + a.id + '">' +
          '<input type="hidden" name="action" value="submit"><input type="hidden" name="assignment_id" value="' + a.id + '">' +
          '<label>Upload submission <input type="file" name="file" class="input" required></label>' +
          '<button type="submit" class="btn">Submit</button></form>';
      }
      return html + '</div>';
    }).join('');

    if (isManager) {
      list.forEach(function (a) {
        LMS.api('/api/lms/assignment_submission?assignment_id=' + a.id).then(function (r) {
          var box = document.getElementById('subs-' + a.id);
          if (!r.ok) { box.innerHTML = '<div class="empty">' + LMS.esc(r.error.message) + '</div>'; return; }
          var subs = (r.data.submissions || []);
          box.innerHTML = subs.length ? subs.map(function (s) {
            return '<div class="row"><div><strong>' + LMS.esc(s.display_name) + '</strong> &middot; <span class="muted">' + LMS.esc(s.submitted_at) + '</span></div>' +
              '<span>' + (s.status === 'graded' ? '<span class="ok">graded</span>' : '<span class="badge">pending</span>') + '</span>' +
              '<a class="btn" href="/download/submission/' + s.id + '">Download</a>' +
              '<form class="grade-form" data-submission="' + s.id + '"><input type="number" name="score" placeholder="score" class="input small" required><button class="btn">Grade</button></form></div>';
          }).join('') : '<div class="empty">No submissions yet.</div>';
          box.querySelectorAll('.grade-form').forEach(function (f) {
            f.addEventListener('submit', function (ev) {
              ev.preventDefault();
              LMS.api('/api/lms/assignment_submission/' + f.dataset.submission, { method: 'PATCH', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ score: parseFloat(f.elements.score.value), status: 'graded' }) }).then(function (rr) {
                if (rr.ok) load(); else alert(rr.error.message);
              });
            });
          });
        });
      });
    } else {
      list.forEach(function (a) {
        LMS.api('/api/lms/assignment_submission?assignment_id=' + a.id).then(function (r) {
          var box = document.getElementById('mysub-' + a.id);
          if (!r.ok) { box.innerHTML = ''; return; }
          var mine = r.data.my_submission;
          box.innerHTML = mine ? '<p class="ok">Submitted: ' + LMS.esc(mine.filename) + ' (' + LMS.esc(mine.status) + ') &middot; <a href="/download/submission/' + mine.id + '">Download</a></p>' : '';
        });
      });
    }
    container.querySelectorAll('.submit-form').forEach(function (f) {
      f.addEventListener('submit', function (e) {
        e.preventDefault();
        LMS.api('/api/lms/assignment_submission', { method: 'POST', body: new FormData(f) }).then(function (r) {
          if (r.ok) { f.reset(); load(); } else { alert(r.error.message); }
        });
      });
    });
  }

  function load() {
    LMS.load(container, LMS.api('/api/lms/assignment_submission?course_id=' + cid), render);
  }
  load();

  var af = document.getElementById('assignment-form');
  if (af) af.addEventListener('submit', function (e) {
    e.preventDefault();
    LMS.api('/api/lms/assignment_submission', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(Object.fromEntries(new FormData(af))) }).then(function (r) {
      if (r.ok) { af.reset(); load(); } else { alert(r.error.message); }
    });
  });
})();
</script>
