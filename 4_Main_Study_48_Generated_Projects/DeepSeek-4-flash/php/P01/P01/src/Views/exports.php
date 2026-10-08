<?php
/** @var array<string, mixed>|null $user */
$role = $user['role_name'];
?>
<h1>Grade Export</h1>
<p class="muted">Export course gradebooks as CSV or PDF. Downloads are recorded as persistent export records.</p>

<?php if ($role === 'admin'): ?>
<label>Course
  <select id="export-course" class="input"></select>
</label>
<label>Format
  <select id="export-format" class="input">
    <option value="csv">CSV</option>
    <option value="pdf">PDF</option>
  </select>
</label>
<button id="export-btn" class="btn btn-primary">Generate export</button>
<div id="export-msg"></div>
<?php else: ?>
<p class="muted">Choose one of your courses below to export its gradebook.</p>
<?php endif; ?>

<h2>Previous exports</h2>
<div id="exports"><div class="empty">Loading exports...</div></div>

<script>
(function () {
  var role = <?= json_encode($role) ?>;
  var courseSel = document.getElementById('export-course');
  var formatSel = document.getElementById('export-format');

  function loadExports() {
    LMS.load(document.getElementById('exports'), LMS.api('/api/lms/grade_export'), function (data) {
      var list = data.exports || [];
      if (!list.length) { document.getElementById('exports').innerHTML = '<div class="empty">No exports yet.</div>'; return; }
      document.getElementById('exports').innerHTML = '<table class="table"><thead><tr><th>Course</th><th>Format</th><th>Status</th><th>Created</th><th>Download</th></tr></thead><tbody>' +
        list.map(function (x) {
          return '<tr><td>' + LMS.esc(x.course_title || 'All') + '</td><td>' + LMS.esc(x.format) + '</td><td>' + LMS.esc(x.status) + '</td><td>' + LMS.esc(x.created_at) + '</td>' +
            '<td><a class="btn" href="/download/export/' + x.id + '">Download</a></td></tr>';
        }).join('') + '</tbody></table>';
    });
  }

  function loadCourses() {
    if (!courseSel) return;
    LMS.api('/api/lms/course_discovery').then(function (r) {
      if (!r.ok) return;
      (r.data.courses || []).forEach(function (c) {
        var opt = document.createElement('option');
        opt.value = c.id;
        opt.textContent = c.title;
        courseSel.appendChild(opt);
      });
    });
  }

  var btn = document.getElementById('export-btn');
  if (btn) btn.addEventListener('click', function () {
    var courseId = parseInt(courseSel.value, 10);
    var format = formatSel.value;
    LMS.api('/api/lms/grade_export', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ course_id: courseId, format: format }) }).then(function (r) {
      document.getElementById('export-msg').innerHTML = r.ok ? '<div class="alert alert-success">Export generated.</div>' : '<div class="alert alert-error">' + LMS.esc(r.error.message) + '</div>';
      if (r.ok) loadExports();
    });
  });

  loadExports();
  loadCourses();
})();
</script>
