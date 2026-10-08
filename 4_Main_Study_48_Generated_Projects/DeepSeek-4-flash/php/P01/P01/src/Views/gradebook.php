<?php
/** @var array<string, mixed> $course */
?>
<nav class="tabs">
  <a href="/courses/<?= (int) $course['id'] ?>">Materials &amp; Announcements</a>
  <a href="/courses/<?= (int) $course['id'] ?>/discussion">Discussion</a>
  <a href="/courses/<?= (int) $course['id'] ?>/assignments">Assignments</a>
  <a href="/courses/<?= (int) $course['id'] ?>/quizzes">Quizzes</a>
  <a href="/courses/<?= (int) $course['id'] ?>/gradebook" class="active">Gradebook</a>
</nav>
<h1>Gradebook &mdash; <?= htmlspecialchars($course['title']) ?></h1>

<details class="panel">
  <summary>Add grade item</summary>
  <form id="item-form">
    <input type="hidden" name="action" value="item">
    <input type="hidden" name="course_id" value="<?= (int) $course['id'] ?>">
    <label>Name <input type="text" name="name" class="input" required></label>
    <label>Max points <input type="number" name="max_points" value="100" class="input" required></label>
    <label>Weight <input type="number" step="0.1" name="weight" value="1" class="input" required></label>
    <button type="submit" class="btn btn-primary">Add item</button>
  </form>
</details>

<div id="gradebook"><div class="empty">Loading gradebook...</div></div>

<script>
(function () {
  var cid = <?= (int) $course['id'] ?>;
  var container = document.getElementById('gradebook');

  function render(data) {
    var gradebook = data.gradebook || [];
    var averages = data.averages || [];
    if (!gradebook.length) { container.innerHTML = '<div class="empty">No grade items or enrolled students yet.</div>'; return; }
    var items = {};
    var students = [];
    gradebook.forEach(function (g) {
      items[g.item_id] = { name: g.item_name, max: g.max_points, weight: g.weight };
      if (!students.find(function (s) { return s.id === g.student_id; })) students.push({ id: g.student_id, username: g.username, display_name: g.display_name });
    });
    var itemIds = Object.keys(items);
    var html = '<table class="table"><thead><tr><th>Student</th>' + itemIds.map(function (id) { return '<th>' + LMS.esc(items[id].name) + ' <span class="muted">/' + items[id].max + '</span></th>'; }).join('') + '<th>Average</th></tr></thead><tbody>';
    html += students.map(function (s) {
      var row = '<tr><td>' + LMS.esc(s.display_name) + ' <span class="muted">(' + LMS.esc(s.username) + ')</span></td>';
      itemIds.forEach(function (id) {
        var cell = gradebook.find(function (g) { return g.student_id === s.id && String(g.item_id) === id; });
        row += '<td><input type="number" step="0.5" class="input small score-input" data-grade="' + (cell && cell.grade_id || '') + '" data-item="' + id + '" data-student="' + s.id + '" value="' + (cell && cell.score != null ? cell.score : '') + '" placeholder="&ndash;"></td>';
      });
      var avg = averages.find(function (a) { return a.username === s.username; });
      row += '<td>' + (avg && avg.weighted_pct != null ? avg.weighted_pct + '%' : '&ndash;') + '</td></tr>';
      return row;
    }).join('') + '</tbody></table>';
    html += '<p class="muted small">Edit a score and press Enter to save. Scores are persisted as grade records.</p>';
    container.innerHTML = html;

    container.querySelectorAll('.score-input').forEach(function (input) {
      input.addEventListener('change', function () {
        var gradeId = parseInt(input.dataset.grade, 10) || 0;
        var payload = { student_id: parseInt(input.dataset.student, 10), grade_item_id: parseInt(input.dataset.item, 10), score: parseFloat(input.value) };
        var path = gradeId ? '/api/lms/grades/' + gradeId : '/api/lms/grades';
        var method = gradeId ? 'PATCH' : 'POST';
        LMS.api(path, { method: method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) }).then(function (r) {
          if (r.ok) load(); else alert(r.error.message);
        });
      });
    });
  }

  function load() {
    LMS.load(container, LMS.api('/api/lms/grades?course_id=' + cid), render);
  }
  load();

  var iform = document.getElementById('item-form');
  iform.addEventListener('submit', function (e) {
    e.preventDefault();
    LMS.api('/api/lms/grades', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(Object.fromEntries(new FormData(iform))) }).then(function (r) {
      if (r.ok) { iform.reset(); load(); } else { alert(r.error.message); }
    });
  });
})();
</script>
