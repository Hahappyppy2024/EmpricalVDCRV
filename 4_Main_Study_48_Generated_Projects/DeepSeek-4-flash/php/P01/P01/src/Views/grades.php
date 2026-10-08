<h1>My Grades</h1>
<div id="grades"><div class="empty">Loading grades...</div></div>

<script>
(function () {
  var container = document.getElementById('grades');
  LMS.load(container, LMS.api('/api/lms/grades'), function (data) {
    var grades = data.grades || [];
    if (!grades.length) { container.innerHTML = '<div class="empty">No grades have been recorded for you yet.</div>'; return; }
    container.innerHTML = '<table class="table"><thead><tr><th>Course</th><th>Item</th><th>Score</th><th>Max</th><th>Feedback</th><th>Graded at</th></tr></thead><tbody>' +
      grades.map(function (g) {
        return '<tr><td>' + LMS.esc(g.course_title) + '</td><td>' + LMS.esc(g.item_name) + '</td>' +
          '<td>' + g.score + '</td><td>' + g.max_points + '</td><td>' + LMS.esc(g.feedback || '') + '</td><td>' + LMS.esc(g.graded_at) + '</td></tr>';
      }).join('') + '</tbody></table>';
  });
})();
</script>
