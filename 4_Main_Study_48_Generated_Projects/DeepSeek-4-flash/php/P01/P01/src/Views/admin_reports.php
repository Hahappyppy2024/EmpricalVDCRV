<h1>Bulk Course Report</h1>
<p class="muted">Generate aggregate reports across courses, users, enrollments and materials (LMS-11). Reports are persisted and reproducible.</p>

<form class="filters" id="report-filters">
  <select name="report_type" class="input">
    <option value="courses">Course summary</option>
    <option value="users">User summary</option>
    <option value="enrollments">Enrollment summary</option>
  </select>
  <select name="semester" class="input"><option value="all">All semesters</option></select>
  <select name="category" class="input"><option value="all">All categories</option></select>
  <select name="status" class="input">
    <option value="all">Any status</option>
    <option value="open">Open</option>
    <option value="closed">Closed</option>
  </select>
  <button type="submit" class="btn btn-primary">Generate report</button>
</form>

<div id="report-result"><div class="empty">Generate a report to see results here.</div></div>

<h2>Previous reports</h2>
<div id="reports"><div class="empty">Loading reports...</div></div>

<script>
(function () {
  var form = document.getElementById('report-filters');
  var result = document.getElementById('report-result');
  var prev = document.getElementById('reports');

  function loadPrev() {
    LMS.load(prev, LMS.api('/api/lms/bulk_course_report'), function (data) {
      var list = data.reports || [];
      if (!list.length) { prev.innerHTML = '<div class="empty">No reports generated yet.</div>'; return; }
      prev.innerHTML = '<table class="table"><thead><tr><th>#</th><th>Type</th><th>Summary</th><th>Created</th></tr></thead><tbody>' +
        list.map(function (r) {
          var summary = r.summary_json;
          try { summary = JSON.parse(summary); } catch (e) { summary = {}; }
          return '<tr><td>' + r.id + '</td><td>' + LMS.esc(r.report_type) + '</td><td>' + LMS.esc(JSON.stringify(summary)) + '</td><td>' + LMS.esc(r.created_at) + '</td></tr>';
        }).join('') + '</tbody></table>';
    });
  }

  function fillOptions() {
    LMS.api('/api/lms/course_discovery').then(function (r) {
      if (!r.ok) return;
      ['semester', 'category'].forEach(function (name) {
        var sel = form.elements[name];
        (r.data.filters[name + 's'] || []).forEach(function (v) {
          var opt = document.createElement('option');
          opt.value = v; opt.textContent = v; sel.appendChild(opt);
        });
      });
    });
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var payload = Object.fromEntries(new FormData(form));
    result.innerHTML = '<div class="empty">Generating...</div>';
    LMS.api('/api/lms/bulk_course_report', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) }).then(function (r) {
      if (!r.ok) { result.innerHTML = '<div class="alert alert-error">' + LMS.esc(r.error.message) + '</div>'; return; }
      var summary = r.data.report.summary_json;
      try { summary = JSON.parse(summary); } catch (err) { summary = {}; }
      result.innerHTML = '<div class="panel"><h3>Report #' + r.data.report.id + ' (' + LMS.esc(r.data.report.report_type) + ')</h3>' +
        '<div class="stat-grid">' +
        '<div class="stat"><strong>' + (summary.total_courses || 0) + '</strong><span>Courses</span></div>' +
        '<div class="stat"><strong>' + (summary.total_users || 0) + '</strong><span>Users</span></div>' +
        '<div class="stat"><strong>' + (summary.total_enrollments || 0) + '</strong><span>Enrollments</span></div>' +
        '<div class="stat"><strong>' + (summary.total_materials || 0) + '</strong><span>Materials</span></div>' +
        '</div></div>';
      loadPrev();
    });
  });

  loadPrev();
  fillOptions();
})();
</script>
