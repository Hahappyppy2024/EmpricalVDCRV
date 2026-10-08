<h1>Course Discovery</h1>
<form class="filters" id="discovery-filters">
  <input type="search" name="q" placeholder="Search courses..." class="input">
  <select name="category" class="input"><option value="all">All categories</option></select>
  <select name="semester" class="input"><option value="all">All semesters</option></select>
  <select name="status" class="input">
    <option value="all">Any status</option>
    <option value="open">Open</option>
    <option value="closed">Closed</option>
  </select>
  <select name="instructor_id" class="input"><option value="all">Any instructor</option></select>
  <button type="submit" class="btn btn-primary">Apply filters</button>
</form>
<div id="course-list" class="card-grid"><div class="empty">Loading courses...</div></div>

<script>
(function () {
  var el = document.getElementById('course-list');
  var form = document.getElementById('discovery-filters');
  var courseId = location.pathname.split('/').filter(Boolean)[1] || 0;

  function render(data) {
    var courses = data.courses || [];
    if (!courses.length) {
      el.innerHTML = '<div class="empty">No courses match your filters.</div>';
      return;
    }
    el.innerHTML = courses.map(function (c) {
      var statusBadge = c.status === 'open' ? 'ok' : 'muted';
      return '<div class="card">' +
        '<span class="badge">' + LMS.esc(c.category) + '</span>' +
        '<h3><a href="/courses/' + c.id + '">' + LMS.esc(c.title) + '</a></h3>' +
        '<p>' + LMS.esc(c.description || '') + '</p>' +
        '<p class="muted">' + LMS.esc(c.semester) + ' &middot; ' + LMS.esc(c.instructor_name || '') +
        ' &middot; <span class="' + statusBadge + '">' + LMS.esc(c.status) + '</span> &middot; ' + c.enrollment_count + ' enrolled</p>' +
        (c.enrolled ? '<span class="badge ok">Enrolled</span>' : '') +
        '</div>';
    }).join('');
  }

  function load(filters) {
    LMS.load(el, LMS.api('/api/lms/course_discovery' + (filters ? '?' + filters : '')), function (data) {
      // populate filter options once
      ['category', 'semester'].forEach(function (name) {
        var select = form.elements[name];
        var existing = select.value;
        (data.filters[name + 's'] || []).forEach(function (value) {
          var opt = document.createElement('option');
          opt.value = value;
          opt.textContent = value;
          if (existing === value) opt.selected = true;
          select.appendChild(opt);
        });
      });
      var instSel = form.elements.instructor_id;
      (data.filters.instructors || []).forEach(function (inst) {
        var opt = document.createElement('option');
        opt.value = inst.id;
        opt.textContent = inst.display_name;
        instSel.appendChild(opt);
      });
      render(data);
    });
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var params = new URLSearchParams(new FormData(form)).toString();
    history.replaceState(null, '', '/courses?' + params);
    load(params);
  });

  load(location.search.replace(/^\?/, ''));
})();
</script>
